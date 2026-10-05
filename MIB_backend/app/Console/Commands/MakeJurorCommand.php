<?php

namespace App\Console\Commands;

use App\Enums\TribunalJurorEligibilityStatus;
use App\Models\TribunalJurorProfile;
use App\Models\User;
use Illuminate\Console\Command;

class MakeJurorCommand extends Command
{
    protected $signature = 'tribunal:make-juror 
                            {user : The user ID or email address}
                            {--status=eligible : The eligibility status (eligible, pending, suspended, inactive)}
                            {--unavailable : Mark the juror as unavailable}';

    protected $description = 'Grant or update juror eligibility status for a user';

    public function handle(): int
    {
        $userIdentifier = $this->argument('user');

        $user = is_numeric($userIdentifier)
            ? User::find($userIdentifier)
            : User::where('email', $userIdentifier)->first();

        if (!$user) {
            $this->error("User '{$userIdentifier}' not found.");
            return Command::FAILURE;
        }

        $statusValue = strtolower($this->option('status'));
        $status = TribunalJurorEligibilityStatus::tryFrom($statusValue);

        if (!$status) {
            $this->error("Invalid status '{$statusValue}'. Allowed: eligible, pending, suspended, inactive.");
            return Command::FAILURE;
        }

        $available = !$this->option('unavailable');

        $profile = TribunalJurorProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => $status,
                'available' => $available,
                'qualified_at' => $status === TribunalJurorEligibilityStatus::Eligible ? now() : null,
                'training_completed_at' => $status === TribunalJurorEligibilityStatus::Eligible ? now() : null,
                'notes' => 'Qualified via tribunal:make-juror artisan command.',
            ]
        );

        $name = $user->profile?->full_name ?: ($user->email ?? "User #{$user->id}");
        $this->info("User #{$user->id} ({$name}) is now {$status->value} as a Tribunal juror (Available: " . ($available ? 'Yes' : 'No') . ').');

        return Command::SUCCESS;
    }
}
