<?php

namespace App\Console\Commands;

use App\Enums\TribunalCaseStatus;
use App\Models\TribunalCase;
use App\Services\Tribunal\TribunalJuryPanelAssignmentService;
use Illuminate\Console\Command;

class TribunalAssignJuryPanelsCommand extends Command
{
    protected $signature = 'tribunal:assign-jury-panels 
                            {--case= : Specific Tribunal Case ID to assign}
                            {--dry-run : Preview cases eligible for Jury Panel assignment without applying changes}';

    protected $description = 'Safely assign active Jury Panels to eligible Tribunal cases awaiting assignment';

    public function __construct(
        protected TribunalJuryPanelAssignmentService $assignmentService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $caseId = $this->option('case');
        $isDryRun = (bool) $this->option('dry-run');

        $query = TribunalCase::query()
            ->where('status', TribunalCaseStatus::JurySelection)
            ->whereDoesntHave('juryPanelAssignments', function ($q) {
                $q->where('status', 'active');
            });

        if ($caseId) {
            $query->where('id', $caseId);
        }

        $eligibleCases = $query->get();

        if ($eligibleCases->isEmpty()) {
            $this->info('No eligible unassigned Tribunal cases found awaiting Jury Panel assignment.');
            return Command::SUCCESS;
        }

        $this->info(sprintf('Found %d eligible case(s) awaiting assignment.', $eligibleCases->count()));

        if ($isDryRun) {
            $this->warn('DRY RUN MODE — No changes will be applied.');
            $headers = ['Case ID', 'Case Number', 'Title', 'Status'];
            $rows = $eligibleCases->map(fn($c) => [$c->id, $c->case_number, $c->title, $c->status->value ?? $c->status]);
            $this->table($headers, $rows);
            return Command::SUCCESS;
        }

        $activePanels = $this->assignmentService->findActivePanels();
        if ($activePanels->isEmpty()) {
            $this->error('Cannot assign cases: No active Jury Panels exist in the system.');
            return Command::FAILURE;
        }

        $assignedCount = 0;
        foreach ($eligibleCases as $case) {
            $assignment = $this->assignmentService->assignCase($case, 'automatic');
            if ($assignment) {
                $assignedCount++;
                $this->line(sprintf(
                    'Assigned Case %s (#%d) -> Panel %s (%s)',
                    $case->case_number,
                    $case->id,
                    $assignment->juryPanel->panel_code,
                    $assignment->juryPanel->panel_name
                ));
            } else {
                $this->warn(sprintf('Could not assign Case %s (#%d).', $case->case_number, $case->id));
            }
        }

        $this->info(sprintf('Successfully assigned %d of %d eligible cases.', $assignedCount, $eligibleCases->count()));
        return Command::SUCCESS;
    }
}
