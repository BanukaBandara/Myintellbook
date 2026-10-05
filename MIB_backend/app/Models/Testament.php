<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Testament extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_AWAITING_WITNESS = 'awaiting_witness';
    public const STATUS_SEALED = 'sealed';
    public const STATUS_WITHDRAWN = 'withdrawn';

    public const WITNESS_PENDING = 'pending';
    public const WITNESS_CONFIRMED = 'confirmed';
    public const WITNESS_DECLINED = 'declined';

    public const TRIBUNAL_NOT_SUBMITTED = 'not_submitted';
    public const TRIBUNAL_AWAITING_REVIEW = 'awaiting_review';

    protected $fillable = [
        'user_id',
        'status',
        'title',
        'instructions',
        'health_declaration',
        'beneficiaries',
        'content_hash',
        'witness_user_id',
        'witness_status',
        'witness_responded_at',
        'consent_given_at',
        'tribunal_status',
        'sealed_at',
        'withdrawn_at',
    ];

    protected $casts = [
        'title' => 'encrypted',
        'instructions' => 'encrypted',
        'health_declaration' => 'encrypted:array',
        'beneficiaries' => 'encrypted:array',
        'witness_responded_at' => 'datetime',
        'consent_given_at' => 'datetime',
        'sealed_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function witness(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witness_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(TestamentAuditLog::class)->orderBy('id');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * SHA-256 over the decrypted content in a canonical form, used to prove the sealed
     * content is exactly what the witness attested to.
     */
    public function computeContentHash(): string
    {
        return hash('sha256', json_encode([
            'title' => (string) $this->title,
            'instructions' => (string) $this->instructions,
            'health_declaration' => $this->health_declaration ?? [],
            'beneficiaries' => $this->beneficiaries ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
