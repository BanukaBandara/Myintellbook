<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserScore;
use App\Http\Requests\AnswerRequest;
use App\Services\DailyAnswerEvaluator;
use App\Services\DailyQuestionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyQuestionController extends Controller
{
    public function getTodayQuestion(Request $request): JsonResponse
    {
        $question = $this->resolveTodayQuestion();

        if (!$question) {
            return response()->json([
                'status' => 'error',
                'message' => 'No daily question is available.',
            ], 404);
        }

        $user = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $this->evaluateStaleAnswers($user->id);
        $answer = UserAnswer::query()
            ->where('user_id', $user->id)
            ->where('question_id', $question->id)
            ->first();
        $legacyAnswer = $answer ? null : Answer::query()
            ->where('user_id', $user->id)
            ->where('question_id', $question->id)
            ->first();

        $answerStatus = $answer?->status ?? ($legacyAnswer ? UserAnswer::STATUS_EVALUATED : null);
        $isEvaluated = $answerStatus === UserAnswer::STATUS_EVALUATED;

        $userScoreToday = $isEvaluated ? ($answer?->score ?? $legacyAnswer?->score) : null;
        if ($userScoreToday === null && $legacyAnswer) {
            $userScoreToday = UserScore::query()
                ->where('user_id', $user->id)
                ->where('source_type', Question::class)
                ->where('source_id', (string) $question->id)
                ->value('calculated_score');
        }

        return response()->json([
            'status' => 'success',
            'has_answered' => $answerStatus !== null,
            'answer_status' => $answerStatus,
            'selected_option_index' => $answer?->selected_option_index ?? $legacyAnswer?->selected_option_index,
            'can_update' => !$isEvaluated,
            'user_score_today' => $userScoreToday,
            'is_correct' => $isEvaluated ? ($answer?->is_correct ?? ($legacyAnswer ? $legacyAnswer->answer_status === 'correct' : null)) : null,
            'question' => [
                'id' => $question->id,
                'category' => $question->category ?: $question->profession?->name,
                'question_text' => $question->question,
                'options' => $this->optionTexts($question->options),
            ],
        ]);
    }

    public function submitDailyAnswer(AnswerRequest $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        try {
            $result = DB::transaction(function () use ($request) {
                $user = User::query()->lockForUpdate()->findOrFail(Auth::id());
                $question = Question::query()->find($request->integer('question_id'));

                if (!$question) {
                    return ['status' => 'missing_question'];
                }

                // Only today's question accepts answers; after midnight it is closed for evaluation.
                if ($this->resolveTodayQuestion()?->id !== $question->id) {
                    return ['status' => 'closed'];
                }

                $index = $request->integer('selected_option_index');
                if (DailyAnswerEvaluator::optionAt($question, $index) === null) {
                    return ['status' => 'invalid_option'];
                }

                $existing = UserAnswer::query()
                    ->where('user_id', $user->id)
                    ->where('question_id', $question->id)
                    ->first();

                $alreadyScored = $existing
                    ? $existing->status !== UserAnswer::STATUS_PENDING
                        || !$existing->answer_date?->isToday()
                    : Answer::query()
                        ->where('user_id', $user->id)
                        ->where('question_id', $question->id)
                        ->exists();

                if ($alreadyScored) {
                    return ['status' => 'closed'];
                }

                $answer = UserAnswer::query()->updateOrCreate(
                    ['user_id' => $user->id, 'question_id' => $question->id],
                    [
                        'selected_option_index' => $index,
                        'answer_date' => today(),
                        'score' => null,
                        'status' => UserAnswer::STATUS_PENDING,
                    ],
                );

                // Scored on submission: awards the points and recalculates the HIP score in this transaction.
                DailyAnswerEvaluator::evaluate($answer->id);

                return [
                    'status' => 'saved',
                    'index' => $index,
                    'answer' => $answer->fresh(),
                    'user' => $user->fresh(),
                ];
            }, 3);

            if ($result['status'] === 'missing_question') {
                return response()->json(['status' => 'error', 'message' => 'Question not found.'], 404);
            }

            if ($result['status'] === 'closed') {
                return response()->json(['status' => 'error', 'message' => 'This question is closed for answers.'], 409);
            }

            if ($result['status'] === 'invalid_option') {
                return response()->json(['status' => 'error', 'message' => 'Selected option is invalid.'], 422);
            }

            $answer = $result['answer'];

            return response()->json([
                'success' => true,
                'status' => 'success',
                'code' => 200,
                'answer_status' => $answer->status,
                'selected_option_index' => $result['index'],
                'is_correct' => $answer->is_correct,
                'points' => $answer->score,
                'total_points' => (float) $result['user']->total_points,
                'hip_score' => (float) $result['user']->hip_score,
                'message' => $answer->is_correct ? 'Correct answer — points awarded.' : 'Answer recorded.',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('DailyQuestionController @submitDailyAnswer: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to submit answer.',
            ], 500);
        }
    }

    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();
        $this->evaluateStaleAnswers((int) $userId);

        $answers = UserAnswer::query()
            ->with('question:id,question,options,category,scheduled_date,profession_id', 'question.profession:id,name')
            ->join('questions', 'questions.id', '=', 'user_answers.question_id')
            ->where('user_answers.user_id', $userId)
            ->whereDate('user_answers.answer_date', '<', today())
            ->orderByRaw('COALESCE(questions.scheduled_date, user_answers.answer_date) DESC')
            ->orderByDesc('user_answers.id')
            ->select('user_answers.*')
            ->get();

        $items = $answers->map(function (UserAnswer $answer): array {
            $question = $answer->question;
            $options = $this->optionTexts($question?->options);
            $isEvaluated = $answer->status === UserAnswer::STATUS_EVALUATED;

            return [
                'id' => $answer->id,
                'question_id' => $answer->question_id,
                'scheduled_date' => ($question?->scheduled_date ?? $answer->answer_date)?->toDateString(),
                'category' => $question?->category ?: $question?->profession?->name,
                'question_text' => $question?->question,
                'selected_option_index' => $answer->selected_option_index,
                'selected_option' => $options[$answer->selected_option_index] ?? null,
                'status' => $answer->status,
                'is_correct' => $isEvaluated ? $answer->is_correct : null,
                'points' => $isEvaluated ? $answer->score : null,
                'evaluated_at' => $answer->evaluated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $items->values(),
        ]);
    }

    /**
     * Answers are scored on submission now; this scores any left pending from before that change
     * (or from a failed evaluation) so they don't stay stuck when the scheduler isn't running.
     */
    private function evaluateStaleAnswers(int $userId): void
    {
        UserAnswer::query()
            ->where('user_id', $userId)
            ->where('status', UserAnswer::STATUS_PENDING)
            ->pluck('id')
            ->each(function (int $id): void {
                try {
                    DailyAnswerEvaluator::evaluate($id);
                } catch (\Throwable $e) {
                    Log::error("DailyQuestionController: evaluating answer {$id} failed: {$e->getMessage()}");
                }
            });
    }

    private function resolveTodayQuestion(): ?Question
    {
        return DailyQuestionResolver::today();
    }

    private function optionTexts(mixed $options): array
    {
        if (!is_array($options)) {
            return [];
        }

        return collect(array_values($options))
            ->map(function ($option) {
                if (is_string($option)) {
                    return $option;
                }

                if (is_array($option)) {
                    $text = $option['text'] ?? $option['value'] ?? null;
                    return is_string($text) ? $text : null;
                }

                return null;
            })
            ->filter(fn ($text) => is_string($text) && trim($text) !== '')
            ->values()
            ->all();
    }
}
