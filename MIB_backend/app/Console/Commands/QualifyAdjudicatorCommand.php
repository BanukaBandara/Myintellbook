<?php

namespace App\Console\Commands;

use App\Enums\TribunalAdjudicatorStatus;
use App\Enums\TribunalQualificationStatus;
use App\Models\TribunalAdjudicatorProfile;
use App\Models\User;
use Illuminate\Console\Command;

class QualifyAdjudicatorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tribunal:qualify-adjudicator 
                            {user : User ID or email address} 
                            {--status=passed : Qualification status (passed, failed, exempted, pending)} 
                            {--score=85 : Qualification exam score} 
                            {--exam-id= : Optional qualification exam ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set qualification exam status and score for a Tribunal adjudicator candidate';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $identifier = $this->argument('user');

        $user = is_numeric($identifier)
            ? User::find((int) $identifier)
            : User::where('email', $identifier)->first();

        if (!$user) {
            $this->error("User '{$identifier}' not found.");
            return Command::FAILURE;
        }

        $statusInput = strtolower((string) $this->option('status'));
        $status = match ($statusInput) {
            'passed' => TribunalQualificationStatus::Passed,
            'failed' => TribunalQualificationStatus::Failed,
            'exempted' => TribunalQualificationStatus::Exempted,
            'pending' => TribunalQualificationStatus::Pending,
            default => TribunalQualificationStatus::NotStarted,
        };

        $score = $this->option('score') !== null ? (int) $this->option('score') : null;
        $examId = $this->option('exam-id') !== null ? (int) $this->option('exam-id') : null;

        $isPassedOrExempted = in_array($status, [
            TribunalQualificationStatus::Passed,
            TribunalQualificationStatus::Exempted,
        ], true);

        // Find or create adjudicator profile
        $verification = $user->professionalVerification;
        $profile = TribunalAdjudicatorProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'professional_verification_id' => $verification?->id,
                'qualification_status' => $status,
                'qualification_score' => $score,
                'qualification_exam_id' => $examId,
                'qualified_at' => $isPassedOrExempted ? now() : null,
                'status' => $isPassedOrExempted ? TribunalAdjudicatorStatus::Eligible : TribunalAdjudicatorStatus::Pending,
                'available' => $isPassedOrExempted,
            ]
        );

        $this->info("Tribunal Adjudicator Profile updated for User ID {$user->id} ({$user->email}):");
        $this->table(
            ['Field', 'Value'],
            [
                ['User ID', $user->id],
                ['Email', $user->email],
                ['Qualification Status', $profile->qualification_status->value],
                ['Qualification Score', $profile->qualification_score ?? 'N/A'],
                ['Adjudicator Status', $profile->status->value],
                ['Available', $profile->available ? 'Yes' : 'No'],
                ['Qualified At', $profile->qualified_at?->toDateTimeString() ?? 'N/A'],
            ]
        );

        return Command::SUCCESS;
    }
}
