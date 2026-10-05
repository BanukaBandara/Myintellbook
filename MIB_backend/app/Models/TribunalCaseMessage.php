<?php

namespace App\Models;

use App\Enums\TribunalCaseMessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TribunalCaseMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tribunal_case_room_id',
        'tribunal_case_id',
        'sender_id',
        'sender_case_role',
        'message_type',
        'body',
        'target_side',
        'parent_message_id',
        'related_evidence_id',
        'procedural',
    ];

    protected $casts = [
        'message_type' => TribunalCaseMessageType::class,
        'procedural' => 'boolean',
    ];

    public function caseRoom(): BelongsTo
    {
        return $this->belongsTo(TribunalCaseRoom::class, 'tribunal_case_room_id');
    }

    public function tribunalCase(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(TribunalCaseMessage::class, 'parent_message_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TribunalCaseMessage::class, 'parent_message_id');
    }

    public function relatedEvidence(): BelongsTo
    {
        return $this->belongsTo(TribunalEvidence::class, 'related_evidence_id');
    }

    public function isProceduralNotice(): bool
    {
        return $this->message_type === TribunalCaseMessageType::ProceduralNotice;
    }

    public function isAdjudicatorQuestion(): bool
    {
        return $this->message_type === TribunalCaseMessageType::AdjudicatorQuestion;
    }
}
