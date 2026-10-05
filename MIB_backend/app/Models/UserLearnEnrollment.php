<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLearnEnrollment extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_STUDIED = 'studied';
    public const STATUS_EXPIRED = 'expired';
    public const PASS_DAYS = 7;
    public const QUESTION_COUNT = 100;

    protected $fillable = [
        'user_id',
        'category_id',
        'question_ids',
        'status',
        'enrolled_at',
        'expires_at',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'question_ids' => 'array',
        'enrolled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('expires_at', '>', now());
    }

    /**
     * Flags the user's passes whose 7 days have run out.
     */
    public static function expireStale(int $userId): void
    {
        static::query()
            ->where('user_id', $userId)
            ->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->update(['status' => self::STATUS_EXPIRED]);
    }
}
