<?php

namespace App\Http\Controllers;

use App\Models\ExamPoolReset;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\User;
use App\Models\UserLearnEnrollment;
use App\Services\HipScoreCalculator;
use App\Support\LearnCategories;
use App\Support\QuestionOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Timed category exams: 30 random questions, 15 minutes, one attempt per category per day.
 */
class ExamSessionController extends Controller
{
    public function categories(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        ExamSession::expireStale($userId);

        $attemptedToday = ExamSession::query()
            ->where('user_id', $userId)
            ->where('started_at', '>=', today())
            ->pluck('category_id')
            ->all();

        $counts = Question::query()
            ->whereIn('category', array_column(LearnCategories::ALL, 'source'))
            ->select('category', DB::raw('count(*) as total'))
            ->groupBy('category')
            ->pluck('total', 'category');

        $inProgress = $this->inProgressSession($userId);

        return response()->json([
            'success' => true,
            'question_count' => ExamSession::QUESTION_COUNT,
            'duration_minutes' => ExamSession::DURATION_MINUTES,
            'categories' => array_map(fn (array $category) => [
                ...$category,
                'question_count' => (int) ($counts[$category['source']] ?? 0),
                'attempted_today' => in_array($category['id'], $attemptedToday, true),
            ], LearnCategories::list()),
            'next_available_at' => today()->addDay()->toIso8601String(),
            'in_progress' => $inProgress ? $this->sessionPayload($inProgress) : null,
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'between:1,'.count(LearnCategories::ALL)],
        ]);
        $category = LearnCategories::find((int) $validated['category_id']);
        $userId = (int) $request->user()->getAuthIdentifier();

        $result = DB::transaction(function () use ($userId, $category) {
            // Serialises concurrent start clicks for the same user.
            User::query()->lockForUpdate()->findOrFail($userId);
            ExamSession::expireStale($userId);

            // A reload mid-exam resumes the running session instead of starting a new one.
            if ($running = $this->inProgressSession($userId)) {
                return ['status' => 'resumed', 'session' => $running];
            }

            $attempted = ExamSession::query()
                ->where('user_id', $userId)
                ->where('category_id', $category['id'])
                ->where('started_at', '>=', today())
                ->exists();
            if ($attempted) {
                return ['status' => 'limit'];
            }

            $now = now();
            ['ids' => $questionIds, 'reset_pool_size' => $resetPoolSize] = $this->pickQuestions($userId, $category, $now);
            if ($questionIds === []) {
                return ['status' => 'empty'];
            }

            return [
                'status' => 'started',
                'reset_pool_size' => $resetPoolSize,
                'session' => ExamSession::create([
                    'user_id' => $userId,
                    'category_id' => $category['id'],
                    'token' => (string) Str::uuid(),
                    'question_ids' => $questionIds,
                    'status' => ExamSession::STATUS_IN_PROGRESS,
                    'started_at' => $now,
                    'expires_at' => $now->copy()->addMinutes(ExamSession::DURATION_MINUTES),
                ]),
            ];
        });

        return match ($result['status']) {
            'limit' => response()->json([
                'success' => false,
                'message' => "You've already taken today's {$category['name']} exam. Try again tomorrow.",
                'next_available_at' => today()->addDay()->toIso8601String(),
            ], 409),
            'empty' => response()->json([
                'success' => false,
                'message' => 'No questions are available for this category yet.',
            ], 422),
            default => response()->json([
                'success' => true,
                'resumed' => $result['status'] === 'resumed',
                'pool_reset' => isset($result['reset_pool_size']),
                ...(isset($result['reset_pool_size']) ? [
                    'message' => "Congratulations! You have completed all {$result['reset_pool_size']} questions in this category. "
                        .'The question pool has been reset for continuous practice.',
                ] : []),
                ...$this->sessionPayload($result['session']),
            ], $result['status'] === 'started' ? 201 : 200),
        };
    }

    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_session_id' => ['required', 'integer'],
            'session_token' => ['required', 'string', 'uuid'],
            'answers' => ['present', 'array', 'max:'.ExamSession::QUESTION_COUNT],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer_id' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);
        $userId = (int) $request->user()->getAuthIdentifier();

        $result = DB::transaction(function () use ($validated, $userId) {
            $session = ExamSession::query()
                ->where('id', $validated['exam_session_id'])
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$session || !hash_equals($session->token, $validated['session_token'])) {
                return ['status' => 'not_found'];
            }

            // Auto-submit and a manual click can race; the second one just gets the stored result.
            if ($session->status === ExamSession::STATUS_COMPLETED) {
                return ['status' => 'completed', 'session' => $session, 'repeat' => true];
            }

            if (!$session->acceptsSubmissionAt(now())) {
                $session->update(['status' => ExamSession::STATUS_EXPIRED, 'score' => 0, 'correct_count' => 0]);

                return ['status' => 'expired'];
            }

            $this->evaluate($session, $validated['answers']);

            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $user->total_points = (float) $user->total_points + $session->score;
            $user->save();
            HipScoreCalculator::recalculate($user);

            return ['status' => 'completed', 'session' => $session, 'repeat' => false];
        }, 3);

        return match ($result['status']) {
            'not_found' => response()->json(['success' => false, 'message' => 'Exam session not found.'], 404),
            'expired' => response()->json([
                'success' => false,
                'message' => 'Time is up. This exam was not submitted within 15 minutes, so no points were awarded.',
            ], 422),
            default => response()->json([
                'success' => true,
                'already_submitted' => $result['repeat'],
                'result' => $this->resultPayload($result['session']),
            ]),
        };
    }

    private function evaluate(ExamSession $session, array $answers): void
    {
        $questionIds = array_map('intval', $session->question_ids);

        // First answer per question wins; anything outside this session's questions is ignored.
        $selected = [];
        foreach ($answers as $answer) {
            $questionId = (int) $answer['question_id'];
            if (in_array($questionId, $questionIds, true) && !array_key_exists($questionId, $selected)) {
                $selected[$questionId] = isset($answer['answer_id']) ? (int) $answer['answer_id'] : null;
            }
        }

        $questions = Question::query()->whereIn('id', $questionIds)->get(['id', 'options'])->keyBy('id');
        $score = 0.0;
        $maxScore = 0.0;
        $correct = 0;

        foreach ($questionIds as $questionId) {
            $question = $questions->get($questionId);
            if (!$question) {
                continue;
            }

            $options = QuestionOptions::normalize($question);
            $maxScore += (float) $options->max('points');

            $chosen = $selected[$questionId] ?? null;
            $option = $chosen === null ? null : $options->get($chosen);
            if ($option) {
                $score += $option['points'];
                $correct += $option['is_correct'] ? 1 : 0;
            }
        }

        $session->update([
            'answers' => array_map(
                fn ($questionId, $answerId) => ['question_id' => $questionId, 'answer_id' => $answerId],
                array_keys($selected),
                array_values($selected),
            ),
            'status' => ExamSession::STATUS_COMPLETED,
            'submitted_at' => now(),
            'correct_count' => $correct,
            'score' => round($score, 2),
            'max_score' => round($maxScore, 2),
        ]);
    }

    /**
     * 30 random questions the user hasn't been served in an exam during the current cycle,
     * preferring ones not already studied (with point values) in a Learn pass.
     *
     * When fewer than 30 unseen questions remain, the cycle is reset: the leftover unseen
     * questions are used first and the rest is filled at random from the full pool. Returns the
     * pool size as `reset_pool_size` when that happened.
     *
     * @return array{ids: int[], reset_pool_size: int|null}
     */
    private function pickQuestions(int $userId, array $category, \DateTimeInterface $now): array
    {
        $pool = Question::query()
            ->where('category', $category['source'])
            ->whereNotNull('options')
            ->outsideDailyRotation()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $lastReset = ExamPoolReset::query()
            ->where('user_id', $userId)
            ->where('category_id', $category['id'])
            ->max('reset_at');

        $seen = array_intersect($pool, $this->questionIdsFrom(
            ExamSession::query()
                ->where('user_id', $userId)
                ->where('category_id', $category['id'])
                ->when($lastReset, fn ($query) => $query->where('started_at', '>=', $lastReset)),
        ));
        $unseen = array_values(array_diff($pool, $seen));

        $studied = $this->questionIdsFrom(
            UserLearnEnrollment::query()->where('user_id', $userId)->where('category_id', $category['id']),
        );
        $fresh = array_values(array_diff($unseen, $studied));
        $studiedUnseen = array_values(array_intersect($unseen, $studied));
        shuffle($fresh);
        shuffle($studiedUnseen);
        $ordered = [...$fresh, ...$studiedUnseen];

        $resetPoolSize = null;
        if (count($unseen) < ExamSession::QUESTION_COUNT && $seen !== []) {
            // Pool exhausted for this cycle: start a new one. Old sessions (and their scores) stay.
            ExamPoolReset::create([
                'user_id' => $userId,
                'category_id' => $category['id'],
                'reset_at' => $now,
                'pool_size' => count($pool),
            ]);
            $resetPoolSize = count($pool);

            $refill = array_values($seen);
            shuffle($refill);
            $ordered = [...$ordered, ...$refill];
        }

        $ids = array_slice($ordered, 0, ExamSession::QUESTION_COUNT);
        // Mix tiers so leftover-unseen and refilled questions aren't bunched together.
        shuffle($ids);

        return ['ids' => $ids, 'reset_pool_size' => $resetPoolSize];
    }

    private function questionIdsFrom($query): array
    {
        return $query->pluck('question_ids')
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function inProgressSession(int $userId): ?ExamSession
    {
        return ExamSession::query()
            ->where('user_id', $userId)
            ->where('status', ExamSession::STATUS_IN_PROGRESS)
            ->where('expires_at', '>', now())
            ->latest('started_at')
            ->first();
    }

    /**
     * Questions with option text only; points and correct answers stay on the server.
     */
    private function sessionPayload(ExamSession $session): array
    {
        $ids = array_map('intval', $session->question_ids);
        $questions = Question::query()->whereIn('id', $ids)->get(['id', 'question', 'options'])->keyBy('id');

        return [
            'exam_session_id' => $session->id,
            'session_token' => $session->token,
            'category' => LearnCategories::find($session->category_id),
            'started_at' => $session->started_at->toIso8601String(),
            'expires_at' => $session->expires_at->toIso8601String(),
            'remaining_seconds' => max(0, (int) floor(now()->diffInSeconds($session->expires_at, false))),
            'questions' => collect($ids)
                ->map(fn (int $id) => $questions->get($id))
                ->filter()
                ->map(fn (Question $question) => [
                    'id' => $question->id,
                    'question_text' => $question->question,
                    'options' => QuestionOptions::normalize($question)
                        ->map(fn (array $option, int $index) => ['id' => $index, 'text' => $option['text']])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function resultPayload(ExamSession $session): array
    {
        $user = User::query()->find($session->user_id, ['id', 'total_points', 'hip_score']);
        $total = count($session->question_ids);
        $maxScore = (float) $session->max_score;

        return [
            'exam_session_id' => $session->id,
            'category' => LearnCategories::find($session->category_id),
            'total_questions' => $total,
            'answered_count' => count(array_filter($session->answers ?? [], fn ($answer) => $answer['answer_id'] !== null)),
            'correct_count' => $session->correct_count,
            'total_score' => $session->score,
            'max_score' => $maxScore,
            'percentage' => $maxScore > 0 ? round($session->score / $maxScore * 100, 1) : 0,
            'points_gained' => $session->score,
            'hip_score' => $user?->hip_score !== null ? (float) $user->hip_score : null,
            'total_points' => $user?->total_points !== null ? (float) $user->total_points : null,
            'submitted_at' => $session->submitted_at?->toIso8601String(),
            'breakdown' => $this->breakdown($session),
        ];
    }

    /**
     * Per-question review shown after submission. These questions are retired from this
     * user's future exams, so revealing the correct answers here is safe.
     */
    private function breakdown(ExamSession $session): array
    {
        $ids = array_map('intval', $session->question_ids);
        $questions = Question::query()->whereIn('id', $ids)->get(['id', 'question', 'options'])->keyBy('id');
        $selected = collect($session->answers ?? [])->pluck('answer_id', 'question_id');

        return collect($ids)
            ->map(function (int $id) use ($questions, $selected) {
                $question = $questions->get($id);
                if (!$question) {
                    return null;
                }

                $options = QuestionOptions::normalize($question);
                $answerId = $selected->get($id);
                $chosen = $answerId === null ? null : $options->get((int) $answerId);

                return [
                    'question_id' => $id,
                    'question_text' => $question->question,
                    'selected_answer' => $chosen ? ['id' => (int) $answerId, 'text' => $chosen['text']] : null,
                    'correct_answers' => $options->where('is_correct', true)->pluck('text')->values()->all(),
                    'points_earned' => $chosen['points'] ?? 0.0,
                    'max_points' => (float) $options->max('points'),
                    'is_correct' => (bool) ($chosen['is_correct'] ?? false),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
