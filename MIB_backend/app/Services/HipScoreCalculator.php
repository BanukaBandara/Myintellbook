<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\ExamSession;
use App\Models\User;
use App\Models\UserAnswer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class HipScoreCalculator
{
    private const ACADEMIC_POINTS = [
        'phd' => 30687.5,
        'masters' => 18412.5,
        'bachelors' => 12275.0,
        'diploma 3y+' => 11047.5,
        'certificate' => 6137.5,
    ];

    private const EXPERIENCE_POINTS_PER_YEAR = [
        'executive' => 2455.0,
        'non-executive' => 1227.5,
        'life care' => 1636.5,
        'general life' => 102.5,
    ];

    private const ACHIEVEMENT_POINTS = [
        'founder' => 36825.0,
        'founder / 20+ yrs company' => 36825.0,
        'author' => 24550.0,
        'author / published book' => 24550.0,
        'patent' => 24550.0,
        'patent holder' => 24550.0,
        'tech startup' => 18412.5,
        'startup' => 18412.5,
        'ngo founder' => 18412.5,
        'tech startup / ngo founder' => 18412.5,
        'registered professional' => 12275.0,
        'iso certified' => 12275.0,
        'iso / compliance certified' => 12275.0,
        'profile verification' => 2455.0,
        'profile completion' => 2455.0,
    ];

    private const PENALTY_POINTS = [
        'ethical/bribery breach' => -30687.5,
        'extremism' => -24550.0,
        'legal conviction' => -18412.5,
        'employment disciplinary action' => -12275.0,
    ];

    /** LCI buckets in display order. */
    public const LCI_CATEGORIES = [
        'tqm' => 'Daily Questions (TQM)',
        'em' => 'Exams (EM)',
        'pcm' => 'Profile Completion (PCM)',
        'education' => 'Education',
        'experience' => 'Experience',
        'formal_recognition' => 'Formal Recognition',
        'others_legal' => 'Others / Legal',
    ];

    /**
     * Recomputes the Life Competency Index (the sum of every verified LCI bucket) and stores it
     * as the user's hip_score.
     */
    public static function recalculate(User $user): float
    {
        $score = round(array_sum(self::breakdown($user)), 2);
        $user->hip_score = $score;
        $user->save();

        return $score;
    }

    /**
     * Verified score per LCI bucket (see LCI_CATEGORIES), unrounded. Only verified sources count:
     * evaluated daily answers, completed exams, verified achievements and confirmed penalties.
     *
     * @return array<string, float>
     */
    public static function breakdown(User $user): array
    {
        $buckets = array_fill_keys(array_keys(self::LCI_CATEGORIES), 0.0);

        if (Schema::hasTable('education')) {
            $educationColumns = [];
            if (Schema::hasColumn('education', 'degree')) {
                $educationColumns[] = 'degree';
            }
            if (Schema::hasColumn('education', 'category')) {
                $educationColumns[] = 'category';
            }

            foreach ($educationColumns === [] ? collect() : $user->educations()->get($educationColumns) as $education) {
                $buckets['education'] += self::educationPoints(
                    $education->category ?? null,
                    $education->degree ?? null,
                );
            }
        }

        if (Schema::hasTable('user_answers')) {
            $userAnswers = $user->userAnswers();
            if (Schema::hasColumn('user_answers', 'status')) {
                $userAnswers->where('status', UserAnswer::STATUS_EVALUATED);
            }

            foreach ($userAnswers->get(['score']) as $answer) {
                $buckets['tqm'] += (float) $answer->score;
            }
        }

        if (Schema::hasTable('exam_sessions')) {
            $buckets['em'] += (float) ExamSession::query()
                ->where('user_id', $user->id)
                ->where('status', ExamSession::STATUS_COMPLETED)
                ->sum('score');
        }

        if (Schema::hasTable('answers')) {
            $legacyAnswers = Answer::query()->where('user_id', $user->id);
            if (Schema::hasTable('user_answers')) {
                $legacyAnswers->whereNotExists(function ($query) use ($user): void {
                    $query->selectRaw('1')
                        ->from('user_answers')
                        ->whereColumn('user_answers.question_id', 'answers.question_id')
                        ->where('user_answers.user_id', $user->id);
                });
            }
            $buckets['tqm'] += (float) $legacyAnswers->sum('score');
        }

        if (Schema::hasTable('work_experiances')) {
            foreach ($user->workExperiances()->get([
                ...array_values(array_filter([
                    Schema::hasColumn('work_experiances', 'positionType') ? 'positionType' : null,
                    Schema::hasColumn('work_experiances', 'position_type') ? 'position_type' : null,
                    Schema::hasColumn('work_experiances', 'title') ? 'title' : null,
                    Schema::hasColumn('work_experiances', 'starting_date') ? 'starting_date' : null,
                    Schema::hasColumn('work_experiances', 'start_date') ? 'start_date' : null,
                    Schema::hasColumn('work_experiances', 'end_date') ? 'end_date' : null,
                    Schema::hasColumn('work_experiances', 'currently_working') ? 'currently_working' : null,
                ])),
            ]) as $experience) {
                $pointsPerYear = self::experiencePoints(
                    $experience->positionType
                        ?? $experience->position_type
                        ?? $experience->title
                        ?? null,
                );
                $startDateValue = $experience->starting_date ?? $experience->start_date ?? null;
                if ($pointsPerYear === 0.0 || empty($startDateValue)) {
                    continue;
                }

                $endDate = ($experience->currently_working ?? false) || empty($experience->end_date)
                    ? Carbon::today()
                    : Carbon::parse($experience->end_date);

                if ($endDate !== null) {
                    $startDate = Carbon::parse($startDateValue);
                    if ($startDate->greaterThan($endDate)) {
                        continue;
                    }

                    $years = $startDate->diffInYears($endDate);
                    $buckets['experience'] += $pointsPerYear * $years;
                }
            }
        }

        $verifiedAchievementCategories = collect();
        if (Schema::hasTable('achievements')) {
            $achievementColumns = ['category'];
            if (Schema::hasColumn('achievements', 'title')) {
                $achievementColumns[] = 'title';
            }

            $verifiedAchievementCategories = $user->achievements()
                ->where('verification_status', 'verified')
                ->get($achievementColumns)
                ->map(function ($achievement): string {
                    $category = self::normalize($achievement->category);

                    return isset(self::ACHIEVEMENT_POINTS[$category]) || $achievement->title === null
                        ? $category
                        : self::normalize($achievement->title);
                })
                ->unique()
                ->values();
        }

        foreach ($verifiedAchievementCategories as $category) {
            // Profile completion/verification is its own LCI bucket (PCM); the rest is formal recognition.
            $bucket = str_contains($category, 'profile completion') || str_contains($category, 'profile verification')
                ? 'pcm'
                : 'formal_recognition';
            $buckets[$bucket] += self::achievementPoints($category);
        }

        $hasAchievementRegisteredProfessional = $verifiedAchievementCategories
            ->contains(fn (string $category): bool => self::achievementPoints($category) === 12275.0
                && str_contains($category, 'registered professional'));

        $hasRegisteredProfessional = Schema::hasTable('professional_verifications')
            && $user->professionalVerifications()
                ->where('verification_status', 'verified')
                ->get()
                ->contains(fn ($verification): bool => $verification->isValid());

        if ($hasRegisteredProfessional && ! $hasAchievementRegisteredProfessional) {
            $buckets['formal_recognition'] += 12275.0;
        }

        if (Schema::hasTable('tribunal_reports')) {
            foreach ($user->tribunalReports()
                ->where('status', 'confirmed')
                ->pluck('violation_type') as $violationType) {
                $buckets['others_legal'] += self::PENALTY_POINTS[self::normalize($violationType)] ?? 0.0;
            }
        }

        return $buckets;
    }

    public static function calculate(User $user): float
    {
        return self::recalculate($user);
    }

    private static function normalize(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'phd / doctorate', 'phd doctorate', 'doctorate' => 'phd',
            'master\'s degree', 'masters degree', 'master' => 'masters',
            'bachelor', "bachelor's", 'bachelor\'s degree', 'bachelors degree' => 'bachelors',
            'diploma', 'diploma 3 years+', 'diploma 3y +', 'diploma (3y or more)' => 'diploma 3y+',
            'certificates', 'certificate (<3y)', 'certificate less than 3 years' => 'certificate',
            'non executive', 'nonexecutive' => 'non-executive',
            'founder / 20+ years company' => 'founder / 20+ yrs company',
            'author / published book' => 'author / published book',
            'tech startup / ngo founder' => 'tech startup / ngo founder',
            'iso / compliance certified' => 'iso / compliance certified',
            'profile completion (pcm)', 'pcm' => 'profile completion',
            'patent holder' => 'patent holder',
            default => $normalized,
        };
    }

    private static function educationPoints(?string $category, ?string $degree): float
    {
        $labels = array_filter([
            strtolower(trim((string) $category)),
            strtolower(trim((string) $degree)),
        ]);
        $label = implode(' ', $labels);

        if (str_contains($label, 'phd') || str_contains($label, 'doctorate')) {
            return self::ACADEMIC_POINTS['phd'];
        }
        if (str_contains($label, 'master')) {
            return self::ACADEMIC_POINTS['masters'];
        }
        if (str_contains($label, 'bachelor') || str_contains($label, 'bsc') || trim($label) === 'degree') {
            return self::ACADEMIC_POINTS['bachelors'];
        }
        if (str_contains($label, 'diploma')) {
            return self::ACADEMIC_POINTS['diploma 3y+'];
        }
        if (str_contains($label, 'certificate')) {
            return self::ACADEMIC_POINTS['certificate'];
        }

        return self::ACADEMIC_POINTS[self::normalize($category)]
            ?? self::ACADEMIC_POINTS[self::normalize($degree)]
            ?? 0.0;
    }

    private static function experiencePoints(?string $value): float
    {
        $normalized = self::normalize($value);
        if (isset(self::EXPERIENCE_POINTS_PER_YEAR[$normalized])) {
            return self::EXPERIENCE_POINTS_PER_YEAR[$normalized];
        }
        if (str_contains($normalized, 'life care')) {
            return self::EXPERIENCE_POINTS_PER_YEAR['life care'];
        }
        if (str_contains($normalized, 'general life')) {
            return self::EXPERIENCE_POINTS_PER_YEAR['general life'];
        }
        if (str_contains($normalized, 'non-executive') || str_contains($normalized, 'nonexecutive')) {
            return self::EXPERIENCE_POINTS_PER_YEAR['non-executive'];
        }
        if (str_contains($normalized, 'executive')) {
            return self::EXPERIENCE_POINTS_PER_YEAR['executive'];
        }

        return 0.0;
    }

    private static function achievementPoints(string $category): float
    {
        if (isset(self::ACHIEVEMENT_POINTS[$category])) {
            return self::ACHIEVEMENT_POINTS[$category];
        }
        if (str_contains($category, 'founder') && str_contains($category, '20+')) {
            return 36825.0;
        }
        if (str_contains($category, 'author') || str_contains($category, 'published book')) {
            return 24550.0;
        }
        if (str_contains($category, 'patent')) {
            return 24550.0;
        }
        if (str_contains($category, 'startup') || str_contains($category, 'ngo founder')) {
            return 18412.5;
        }
        if (str_contains($category, 'registered professional')) {
            return 12275.0;
        }
        if (str_contains($category, 'iso') || str_contains($category, 'compliance')) {
            return 12275.0;
        }
        if (str_contains($category, 'profile completion') || str_contains($category, 'profile verification')) {
            return 2455.0;
        }

        return 0.0;
    }
}
