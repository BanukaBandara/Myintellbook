<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\PasswordResetToken;

class PasswordResetTokenJob implements ShouldQueue
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
       
        $model = PasswordResetToken::find($this->modelKey);

        if ($model) {
            $model->forceDelete();
        }
    }
}
