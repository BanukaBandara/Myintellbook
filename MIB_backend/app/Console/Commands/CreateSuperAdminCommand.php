<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create {--email= : Admin email} {--name= : Admin name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision a new Super Administrator account safely';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Create Super Administrator Account ===');

        $name = $this->option('name') ?: $this->ask('Administrator Name');
        if (empty(trim($name))) {
            $name = 'Super Administrator';
        }

        $email = $this->option('email') ?: $this->ask('Administrator Email');
        $email = trim(strtolower($email));

        $emailValidator = Validator::make(['email' => $email], [
            'email' => 'required|email|max:255',
        ]);

        if ($emailValidator->fails()) {
            $this->error('The provided email address is invalid.');
            return Command::FAILURE;
        }

        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            if ($existingUser->isAdmin()) {
                $this->warn("A Super Administrator with the email '{$email}' already exists.");
                return Command::FAILURE;
            }

            if ($this->confirm("User '{$email}' exists but is not an administrator. Promote to Super Admin?")) {
                $existingUser->is_admin = true;
                $existingUser->save();
                $this->info("User '{$email}' has been promoted to Super Administrator.");
                return Command::SUCCESS;
            }

            $this->error("Cannot create administrator: Email '{$email}' is already in use by a regular user.");
            return Command::FAILURE;
        }

        $password = $this->secret('Enter Password');
        if (empty($password) || strlen($password) < 8) {
            $this->error('Password must be at least 8 characters long.');
            return Command::FAILURE;
        }

        $passwordConfirm = $this->secret('Confirm Password');
        if ($password !== $passwordConfirm) {
            $this->error('Password confirmation does not match.');
            return Command::FAILURE;
        }

        $admin = new User();
        $admin->email = $email;
        $admin->password = Hash::make($password);
        $admin->is_admin = true;
        $admin->email_verified_at = now();
        $admin->save();

        $this->info("Super Administrator '{$email}' created successfully (User ID: {$admin->id}).");
        return Command::SUCCESS;
    }
}
