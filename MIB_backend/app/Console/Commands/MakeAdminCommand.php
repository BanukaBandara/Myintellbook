<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:make-admin {user : User ID or email address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant administrator / reviewer privileges to a user';

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

        $user->is_admin = true;
        $user->save();

        $this->info("User ID {$user->id} ({$user->email}) is now an administrator / reviewer.");
        return Command::SUCCESS;
    }
}
