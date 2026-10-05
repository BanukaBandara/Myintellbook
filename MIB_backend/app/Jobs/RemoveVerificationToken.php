<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class RemoveVerificationToken implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int|string $modelKey;
    /**
     * Create a new job instance.
     */
    public function __construct($modelKey)
    {
        $this->modelKey = $modelKey;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $model = User::find($this->modelKey);

        if ($model) {
            $model->email_verification_token = null;
            $model->email_verified_at = null;
            $model->save();
        }
    }
}
