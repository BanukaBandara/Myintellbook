<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only, hash-chained audit and consent log. Entries never hold testament content,
 * only what happened, who did it and non-sensitive metadata.
 */
class TestamentAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['testament_id', 'event', 'actor_user_id', 'details', 'prev_hash', 'hash', 'created_at'];

    protected $casts = [
        'details' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Testament audit log entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Testament audit log entries are immutable.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Appends an entry chained to the testament's previous one. Call inside a transaction
     * that has locked the testament row so concurrent writes can't fork the chain.
     */
    public static function record(Testament $testament, string $event, ?int $actorId, array $details = []): self
    {
        $prevHash = static::query()->where('testament_id', $testament->id)->orderByDesc('id')->value('hash');
        $createdAt = now()->format('Y-m-d H:i:s');

        return static::create([
            'testament_id' => $testament->id,
            'event' => $event,
            'actor_user_id' => $actorId,
            'details' => $details ?: null,
            'prev_hash' => $prevHash,
            'hash' => self::hashFor($prevHash, $testament->id, $event, $actorId, $details ?: null, $createdAt),
            'created_at' => $createdAt,
        ]);
    }

    public static function hashFor(?string $prevHash, int $testamentId, string $event, ?int $actorId, ?array $details, string $createdAt): string
    {
        return hash('sha256', implode('|', [
            $prevHash ?? 'GENESIS',
            $testamentId,
            $event,
            $actorId ?? '',
            json_encode(self::canonical($details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $createdAt,
        ]));
    }

    private static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => self::canonical($item), $value);
    }

    /**
     * Recomputes every hash and link; false if any entry was altered, removed or reordered.
     */
    public static function verifyChain(int $testamentId): bool
    {
        $prev = null;
        foreach (static::query()->where('testament_id', $testamentId)->orderBy('id')->cursor() as $entry) {
            $expected = self::hashFor(
                $prev,
                $testamentId,
                $entry->event,
                $entry->actor_user_id,
                $entry->details,
                (string) $entry->getRawOriginal('created_at'),
            );
            if ($entry->prev_hash !== $prev || !hash_equals($expected, $entry->hash)) {
                return false;
            }
            $prev = $entry->hash;
        }

        return true;
    }
}
