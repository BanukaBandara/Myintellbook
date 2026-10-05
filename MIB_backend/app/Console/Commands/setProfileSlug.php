<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\Profile;

class setProfileSlug extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:set-profile-slug';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Profile::chunk(100, function ($users) {
            foreach ($users as $user) {
                if (empty($user->slug)) {
                    $user->slug = Str::slug($user->first_name . ' ' . $user->last_name);
                }
                if (empty($user->uuid)) {
                    $user->uuid = substr(Str::uuid()->toString(), 0, 8);
                }
                $user->save();
            }
        });

        $this->info('Slugs and UUIDs updated for existing users!');
    }
}
