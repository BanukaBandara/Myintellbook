<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\ExamSession;
use App\Models\User;
use App\Models\UserAnswer;
use App\Support\LearnCategories;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Per-record score history behind each Score page tab. Points come from HipScoreCalculator, so
 * every tab's total matches its bucket in the LCI breakdown.
 *
 * Every item has the same shape: id, title, subtitle, date, status, points.
 */
class ScoreFetchService
{
    public function getAllScores(int $userId): array
    {
        $user = User::query()->findOrFail($userId);
        [$identity, $formalRecognition] = $this->achievements($user);

        $sections = [
            'identity' => $identity,
            'education' => $this->education($user),
            'experience' => $this->experience($user),
            'formal_recognition' => $formalRecognition,
            'daily_questions' => $this->dailyQuestions($user),
            'exams' => $this->exams($user),
            'others' => $this->others($user),
        ];

        return [
            ...$sections,
            'totalScore' => round(collect($sections)->flatten(1)->sum('points'), 2),
        ];
    }

    private function education(User $user): array
    {
        if (! Schema::hasTable('education')) {
            return [];
        }

        return $user->educations()->latest('id')->get()
            ->map(fn ($education) => $this->item(
                $education->id,
                $education->degree ?: ($education->field_of_study ?: 'Education'),
                $education->category,
                null,
                'verified',
                HipScoreCalculator::educationPoints($education->category, $education->degree),
            ))
            ->all();
    }

    private function experience(User $user): array
    {
        if (! Schema::hasTable('work_experiances')) {
            return [];
        }

        return $user->workExperiances()->latest('id')->get()
            ->map(fn ($experience) => $this->item(
                $experience->id,
                $experience->company ?: 'Experience',
                $experience->positionType ?: $experience->title,
                $experience->starting_date,
                'verified',
                HipScoreCalculator::experienceRecordPoints($experience),
            ))
            ->all();
    }

    /**
     * Verified achievements split into identity (PCM) and formal recognition. A repeated
     * achievement is listed but earns nothing, matching the LCI's once-per-category rule.
     *
     * @return array{0: array, 1: array}
     */
    private function achievements(User $user): array
    {
        $identity = [];
        $formalRecognition = [];
        $credited = [];

        if (Schema::hasTable('achievements')) {
            foreach ($user->achievements()->where('verification_status', 'verified')->oldest('id')->get() as $achievement) {
                $key = HipScoreCalculator::achievementKey($achievement);
                $isDuplicate = isset($credited[$key]);
                $credited[$key] = true;

                $item = $this->item(
                    $achievement->id,
                    $achievement->title ?: $achievement->category,
                    $achievement->title ? $achievement->category : null,
                    $achievement->verified_at,
                    $isDuplicate ? 'already credited' : 'verified',
                    $isDuplicate ? 0.0 : HipScoreCalculator::achievementPoints($key),
                );

                if (HipScoreCalculator::achievementBucket($key) === 'pcm') {
                    $identity[] = $item;
                } else {
                    $formalRecognition[] = $item;
                }
            }
        }

        $hasRegisteredProfessionalAchievement = collect(array_keys($credited))
            ->contains(fn (string $key): bool => str_contains($key, 'registered professional')
                && HipScoreCalculator::achievementPoints($key) === 12275.0);

        if (Schema::hasTable('professional_verifications')) {
            $verification = $user->professionalVerifications()
                ->where('verification_status', 'verified')
                ->get()
                ->first(fn ($verification): bool => $verification->isValid());

            if ($verification !== null) {
                $formalRecognition[] = $this->item(
                    'professional-'.$verification->id,
                    'Registered professional',
                    $verification->profession_type?->label(),
                    $verification->verified_at,
                    $hasRegisteredProfessionalAchievement ? 'already credited' : 'verified',
                    $hasRegisteredProfessionalAchievement ? 0.0 : 12275.0,
                );
            }
        }

        return [$identity, $formalRecognition];
    }

    private function dailyQuestions(User $user): array
    {
        $items = collect();
        $hasUserAnswers = Schema::hasTable('user_answers');
        $hasStatus = $hasUserAnswers && Schema::hasColumn('user_answers', 'status');

        if ($hasUserAnswers) {
            $items = $user->userAnswers()->with('question:id,question,category')->get()
                ->map(function (UserAnswer $answer) use ($hasStatus) {
                    $pending = $hasStatus && $answer->status !== UserAnswer::STATUS_EVALUATED;

                    return $this->item(
                        'answer-'.$answer->id,
                        $answer->question?->question ?? 'Daily question',
                        $answer->question?->category,
                        $answer->answer_date ?? $answer->created_at,
                        $pending ? 'pending' : ($answer->is_correct ? 'correct' : 'incorrect'),
                        $pending ? 0.0 : (float) $answer->score,
                    );
                });
        }

        if (Schema::hasTable('answers')) {
            $legacy = Answer::query()->with('question:id,question,category')->where('user_id', $user->id);
            if ($hasUserAnswers) {
                $legacy->whereNotExists(function ($query) use ($user): void {
                    $query->selectRaw('1')
                        ->from('user_answers')
                        ->whereColumn('user_answers.question_id', 'answers.question_id')
                        ->where('user_answers.user_id', $user->id);
                });
            }

            $items = $items->concat($legacy->get()->map(fn (Answer $answer) => $this->item(
                'legacy-'.$answer->id,
                $answer->question?->question ?? 'Daily question',
                $answer->question?->category,
                $answer->created_at,
                $answer->answer_status ?: 'evaluated',
                (float) $answer->score,
            )));
        }

        return $items->sortByDesc('date')->values()->all();
    }

    private function exams(User $user): array
    {
        if (! Schema::hasTable('exam_sessions')) {
            return [];
        }

        return ExamSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_EXPIRED])
            ->latest('started_at')
            ->get()
            ->map(fn (ExamSession $session) => $this->item(
                $session->id,
                LearnCategories::find($session->category_id)['name'] ?? 'Exam',
                $session->status === ExamSession::STATUS_COMPLETED
                    ? sprintf('%d / %d correct', $session->correct_count, count($session->question_ids ?? []))
                    : 'Time ran out before submission',
                $session->submitted_at ?? $session->started_at,
                $session->status,
                // Only completed sessions count towards the LCI.
                $session->status === ExamSession::STATUS_COMPLETED ? (float) $session->score : 0.0,
            ))
            ->all();
    }

    private function others(User $user): array
    {
        $items = collect();

        if (Schema::hasTable('tribunal_reports')) {
            $items = $items->concat($user->tribunalReports()->where('status', 'confirmed')->latest('confirmed_at')->get()
                ->map(fn ($report) => $this->item(
                    $report->id,
                    ucfirst((string) $report->violation_type),
                    'Confirmed tribunal finding',
                    $report->confirmed_at,
                    'confirmed',
                    HipScoreCalculator::penaltyPoints($report->violation_type),
                )));
        }

        if (Schema::hasTable('internal_penalties')) {
            $penalties = \App\Models\InternalPenalty::query()
                ->where('user_id', $user->id)
                ->where('action_type', \App\Enums\InternalPenaltyType::HipScorePenalty->value)
                ->whereNull('reversed_at')
                ->where(function ($query) {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })
                ->latest('applied_at')
                ->get();

            $items = $items->concat($penalties->map(fn ($penalty) => $this->item(
                'internal-penalty-' . $penalty->id,
                'Disciplinary Score Deduction',
                'Administrative tribunal penalty',
                $penalty->applied_at,
                'confirmed',
                -abs((float) $penalty->penalty_value),
            )));
        }

        return $items->sortByDesc('date')->values()->all();
    }

    private function item(int|string $id, ?string $title, ?string $subtitle, mixed $date, string $status, float $points): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'subtitle' => $subtitle,
            'date' => $date ? Carbon::parse($date)->toDateString() : null,
            'status' => $status,
            'points' => round($points, 2),
        ];
    }
}
