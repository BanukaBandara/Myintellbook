<?php

namespace App\Models;

use App\Enums\TribunalConversationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TribunalConversation extends Model
{
    protected $fillable = [
        'tribunal_case_id',
        'type',
        'client_user_id',
        'representative_user_id',
        'active',
    ];

    protected $casts = [
        'type' => TribunalConversationType::class,
        'active' => 'boolean',
    ];

    public function tribunalCase(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TribunalMessage::class, 'conversation_id')->orderBy('created_at', 'asc');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(TribunalMessage::class, 'conversation_id')->latestOfMany();
    }

    public function isParticipant(int $userId): bool
    {
        return $this->client_user_id === $userId || $this->representative_user_id === $userId;
    }

    public function otherParticipant(int $userId): ?User
    {
        if ($this->client_user_id === $userId) {
            return $this->representative;
        }

        if ($this->representative_user_id === $userId) {
            return $this->client;
        }

        return null;
    }
}
