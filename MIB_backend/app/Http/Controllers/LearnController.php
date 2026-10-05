<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\User;
use App\Models\UserLearnEnrollment;
use App\Support\LearnCategories;
use App\Support\QuestionOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LearnController extends Controller
{
    private const QUESTION_BATCH_SIZE = 100;

    public function categories(): JsonResponse
    {
        $categories = array_map(fn (array $category) => [
            ...$category,
            'question_count' => $this->learnableQuestions($category['source'])->count(),
        ], LearnCategories::list());

        return response()->json([
            'success' => true,
            'categories' => $categories,
            'points_awarded' => 0,
            'lci_points_awarded' => 0,
            'hip_points_awarded' => 0,
        ]);
    }

    public function questions(int $category): JsonResponse
    {
        $categoryDetails = LearnCategories::find($category);
        if (!$categoryDetails) {
            return response()->json([
                'success' => false,
                'message' => 'The selected learning topic does not exist.',
            ], 404);
        }

        $poolSize = $this->learnableQuestions($categoryDetails['source'])->count();
        if ($poolSize === 0) {
            return response()->json([
                'success' => false,
                'message' => 'There are no learning questions available for this topic yet.',
                'category' => $categoryDetails,
                'pool_size' => 0,
            ], 422);
        }

        $questions = $this->learnableQuestions($categoryDetails['source'])
            ->inRandomOrder()
            ->limit(self::QUESTION_BATCH_SIZE)
            ->get(['id', 'question', 'options'])
            ->map(fn (Question $question) => $this->formatQuestion($question))
            ->filter(fn (array $question) => $question['options'] !== [])
            ->values();

        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'There are no learning questions available for this topic yet.',
                'category' => $categoryDetails,
                'pool_size' => $poolSize,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'active' => true,
            'category' => $categoryDetails,
            'pool_size' => $poolSize,
            'batch_size' => $questions->count(),
            'questions' => $questions,
            'score' => 0,
            'points_awarded' => 0,
            'lci_points_awarded' => 0,
            'hip_points_awarded' => 0,
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        UserLearnEnrollment::expireStale($userId);

        $enrollment = UserLearnEnrollment::query()
            ->where('user_id', $userId)
            ->active()
            ->latest('enrolled_at')
            ->first();

        if (! $enrollment) {
            return response()->json([
                'success' => true,
                'active' => false,
                'categories' => $this->categoriesWithCounts($userId),
            ]);
        }

        return response()->json([
            'success' => true,
            'active' => true,
            ...$this->sessionPayload($enrollment),
        ]);
    }

    public function enroll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'between:1,'.count(LearnCategories::ALL)],
        ]);
        $category = LearnCategories::find((int) $validated['category_id']);
        $userId = (int) $request->user()->getAuthIdentifier();

        $result = DB::transaction(function () use ($userId, $category) {
            // Serialises concurrent enroll clicks for the same user.
            User::query()->lockForUpdate()->findOrFail($userId);
            UserLearnEnrollment::expireStale($userId);

            $existing = UserLearnEnrollment::query()->where('user_id', $userId)->active()->first();
            if ($existing) {
                return ['status' => 'already_active', 'enrollment' => $existing];
            }

            $seenIds = $this->previouslyLearnedQuestionIds($userId, $category['id']);
            $questionIds = $this->learnableQuestions($category['source'])
                ->when($seenIds !== [], fn ($query) => $query->whereNotIn('id', $seenIds))
                ->inRandomOrder()
                ->limit(UserLearnEnrollment::QUESTION_COUNT)
                ->pluck('id')
                ->all();

            if ($questionIds === []) {
                return ['status' => 'empty'];
            }

            $now = now();

            return [
                'status' => 'created',
                'enrollment' => UserLearnEnrollment::create([
                    'user_id' => $userId,
                    'category_id' => $category['id'],
                    'question_ids' => $questionIds,
                    'status' => UserLearnEnrollment::STATUS_ACTIVE,
                    'enrolled_at' => $now,
                    'expires_at' => $now->copy()->addDays(UserLearnEnrollment::PASS_DAYS),
                ]),
            ];
        });

        if ($result['status'] === 'already_active') {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active 7-day learn pass. Choose a new category after it expires.',
                'active' => true,
                ...$this->sessionPayload($result['enrollment']),
            ], 409);
        }

        if ($result['status'] === 'empty') {
            return response()->json([
                'success' => false,
                'message' => 'No questions are available for this category yet.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your 7-day learn pass has started.',
            'active' => true,
            ...$this->sessionPayload($result['enrollment']),
        ], 201);
    }

    public function nextBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollment_id' => ['required', 'integer', 'min:1'],
        ]);
        $userId = (int) $request->user()->getAuthIdentifier();

        $result = DB::transaction(function () use ($userId, $validated) {
            // Serialises completion requests and prevents two next batches being created at once.
            User::query()->lockForUpdate()->findOrFail($userId);
            UserLearnEnrollment::expireStale($userId);

            $enrollment = UserLearnEnrollment::query()
                ->where('user_id', $userId)
                ->whereKey((int) $validated['enrollment_id'])
                ->active()
                ->first();

            if (!$enrollment) {
                $active = UserLearnEnrollment::query()
                    ->where('user_id', $userId)
                    ->active()
                    ->latest('enrolled_at')
                    ->first();

                return ['status' => 'stale', 'enrollment' => $active];
            }

            $category = LearnCategories::find($enrollment->category_id);
            $seenIds = $this->previouslyLearnedQuestionIds($userId, $enrollment->category_id);
            $questionIds = $this->learnableQuestions($category['source'])
                ->when($seenIds !== [], fn ($query) => $query->whereNotIn('id', $seenIds))
                ->inRandomOrder()
                ->limit(UserLearnEnrollment::QUESTION_COUNT)
                ->pluck('id')
                ->all();

            $enrollment->update(['status' => UserLearnEnrollment::STATUS_STUDIED]);

            if ($questionIds === []) {
                return ['status' => 'exhausted'];
            }

            $now = now();
            $nextEnrollment = UserLearnEnrollment::create([
                'user_id' => $userId,
                'category_id' => $enrollment->category_id,
                'question_ids' => $questionIds,
                'status' => UserLearnEnrollment::STATUS_ACTIVE,
                'enrolled_at' => $now,
                'expires_at' => $enrollment->expires_at,
            ]);

            return ['status' => 'created', 'enrollment' => $nextEnrollment];
        });

        if ($result['status'] === 'stale') {
            return response()->json([
                'success' => false,
                'message' => 'This learning set is no longer active. Your current progress has been refreshed.',
                'active' => $result['enrollment'] !== null,
                ...($result['enrollment']
                    ? $this->sessionPayload($result['enrollment'])
                    : ['categories' => $this->categoriesWithCounts($userId)]),
                'points_awarded' => 0,
                'lci_points_awarded' => 0,
                'hip_points_awarded' => 0,
            ], 409);
        }

        if ($result['status'] === 'exhausted') {
            return response()->json([
                'success' => true,
                'message' => 'You have studied all currently available questions in this category.',
                'active' => false,
                'progress_status' => 'Studied',
                'score' => 0,
                'points_awarded' => 0,
                'lci_points_awarded' => 0,
                'hip_points_awarded' => 0,
                'categories' => $this->categoriesWithCounts($userId),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your completed learning set was marked as studied. Here is your next set.',
            'active' => true,
            'progress_status' => 'Studied',
            'score' => 0,
            'points_awarded' => 0,
            'lci_points_awarded' => 0,
            'hip_points_awarded' => 0,
            ...$this->sessionPayload($result['enrollment']),
        ]);
    }

    /**
     * Questions in a category whose scores are safe to reveal: today's daily question and
     * anything scheduled as a future daily question are held back so Learn can't leak them.
     */
    private function learnableQuestions(string $source)
    {
        return Question::query()
            ->where('category', $source)
            ->whereNotNull('options')
            ->outsideDailyRotation();
    }

    private function previouslyLearnedQuestionIds(int $userId, int $categoryId): array
    {
        return UserLearnEnrollment::query()
            ->where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->pluck('question_ids')
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function sessionPayload(UserLearnEnrollment $enrollment): array
    {
        $ids = array_map('intval', $enrollment->question_ids ?? []);
        $questions = Question::query()
            ->whereIn('id', $ids)
            ->get(['id', 'question', 'options'])
            ->keyBy('id');

        // Keep the order chosen at enrollment.
        $items = collect($ids)
            ->map(fn (int $id) => $questions->get($id))
            ->filter()
            ->map(fn (Question $question) => $this->formatQuestion($question))
            ->filter(fn (array $question) => $question['options'] !== [])
            ->values();

        return [
            'score' => 0,
            'points_awarded' => 0,
            'lci_points_awarded' => 0,
            'hip_points_awarded' => 0,
            'enrollment' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'category' => LearnCategories::find($enrollment->category_id),
                'enrolled_at' => $enrollment->enrolled_at->toIso8601String(),
                'expires_at' => $enrollment->expires_at->toIso8601String(),
                'remaining_seconds' => max(0, (int) floor(now()->diffInSeconds($enrollment->expires_at, false))),
            ],
            'questions' => $items,
        ];
    }

    private function formatQuestion(Question $question): array
    {
        $options = QuestionOptions::normalize($question)->values();

        return [
            'id' => $question->id,
            'question_text' => $question->question,
            'max_points' => $options->max('points'),
            'correct_answers' => $options->where('is_correct', true)->pluck('text')->values()->all(),
            'options' => $options->all(),
        ];
    }

    private function categoriesWithCounts(int $userId): array
    {
        $counts = Question::query()
            ->whereIn('category', array_column(LearnCategories::ALL, 'source'))
            ->select('category', DB::raw('count(*) as total'))
            ->groupBy('category')
            ->pluck('total', 'category');
        $seenCounts = UserLearnEnrollment::query()
            ->where('user_id', $userId)
            ->get(['category_id', 'question_ids'])
            ->groupBy('category_id')
            ->map(fn ($enrollments) => $enrollments
                ->pluck('question_ids')
                ->flatten()
                ->unique()
                ->count());

        return array_map(fn (array $category) => [
            ...$category,
            'question_count' => min(
                UserLearnEnrollment::QUESTION_COUNT,
                max(0, (int) ($counts[$category['source']] ?? 0) - (int) ($seenCounts[$category['id']] ?? 0)),
            ),
        ], LearnCategories::list());
    }
}
