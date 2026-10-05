<?php

namespace App\Models;

use App\Enums\TribunalCaseRoomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TribunalCaseRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'tribunal_case_id',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'status' => TribunalCaseRoomStatus::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function tribunalCase(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TribunalCaseMessage::class, 'tribunal_case_room_id');
    }

    public function isActive(): bool
    {
        return $this->status === TribunalCaseRoomStatus::Active;
    }

    /**
     * Check if a user is an authorized participant in the case room.
     */
    public function isParticipant(int $userId): bool
    {
        return $this->tribunalCase->isAuthorizedToView($userId);
    }
}
