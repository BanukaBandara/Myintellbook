<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Answer;
use App\Models\UserScore;
use App\Http\Requests\AnswerRequest;
use App\Notifications\NewUserNotification;
use Illuminate\Support\Facades\Log;

class QuestionController extends Controller
{

    private const DAILY_QUESTION_SCORING_ITEM_ID = 7;

    public function submitDailyAnswer(AnswerRequest $request)
    {
        $user = User::query()->findOrFail(Auth::id());

        try {
            $result = DB::transaction(function () use ($request, $user) {
                $question = Question::query()
                    ->lockForUpdate()
                    ->find($request->integer('question_id'));

                if (!$question) {
                    return ['status' => 'missing_question'];
                }

                if (Answer::query()
                    ->where('user_id', $user->id)
                    ->where('question_id', $question->id)
                    ->exists()) {
                    return ['status' => 'duplicate'];
                }

                $options = $question->options;
                $selectedOption = is_array($options)
                    ? ($options[$request->integer('selected_option_index')] ?? null)
                    : null;

                if (!is_array($selectedOption)
                    || !isset($selectedOption['text'], $selectedOption['score'])
                    || !is_numeric($selectedOption['score'])) {
                    return ['status' => 'invalid_option'];
                }

                $score = (float) $selectedOption['score'];
                if ($score < 0 || $score > 5) {
                    return ['status' => 'invalid_option'];
                }

                Answer::create([
                    'user_id' => $user->id,
                    'question_id' => $question->id,
                    'answer' => (string) $selectedOption['text'],
                    'answer_status' => $score === 5.0 ? 'correct' : 'incorrect',
                ]);

                UserScore::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'scoring_item_id' => self::DAILY_QUESTION_SCORING_ITEM_ID,
                        'source_type' => Question::class,
                        'source_id' => (string) $question->id,
                    ],
                    [
                        'input_value' => (string) $score,
                        'calculated_score' => $score,
                    ]
                );

                $user->notify(new NewUserNotification("You have answered today's question! The answer will be published tomorrow."));

                return ['status' => 'submitted'];
            }, 3);

            if ($result['status'] === 'missing_question') {
                return response()->json(['code' => 404, 'message' => 'Question not found.'], 404);
            }

            if ($result['status'] === 'duplicate') {
                return response()->json(['code' => 409, 'message' => 'You have already answered this question.'], 409);
            }

            if ($result['status'] === 'invalid_option') {
                return response()->json(['code' => 422, 'message' => 'Selected option is invalid.'], 422);
            }

            return response()->json(['code' => 200, 'message' => 'Answer submitted.'], 200);
        } catch (\Throwable $e) {
            Log::error('QuestionController @submitDailyAnswer: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Unable to submit answer.',
            ], 500);
        }
    }

}
