<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSession extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';

    public const QUESTION_COUNT = 30;
    public const DURATION_MINUTES = 15;
    // Allowance for network latency on the auto-submit fired at 00:00.
    public const GRACE_SECONDS = 60;

    protected $fillable = [
        'user_id',
        'category_id',
        'token',
        'question_ids',
        'answers',
        'status',
        'started_at',
        'expires_at',
        'submitted_at',
        'correct_count',
        'score',
        'max_score',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'question_ids' => 'array',
        'answers' => 'array',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'submitted_at' => 'datetime',
        'correct_count' => 'integer',
        'score' => 'float',
        'max_score' => 'float',
    ];

    protected $hidden = ['token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acceptsSubmissionAt(\DateTimeInterface $moment): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS
            && $moment <= $this->expires_at->copy()->addSeconds(self::GRACE_SECONDS);
    }

    /**
     * Unsubmitted sessions past their deadline (plus grace) can no longer earn points.
     */
    public static function expireStale(int $userId): void
    {
        static::query()
            ->where('user_id', $userId)
            ->where('status', self::STATUS_IN_PROGRESS)
            ->where('expires_at', '<', now()->subSeconds(self::GRACE_SECONDS))
            ->update(['status' => self::STATUS_EXPIRED, 'score' => 0, 'correct_count' => 0]);
    }
}
