<?php

namespace App\Models;

use App\Enums\TribunalPartyRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalCaseParty extends Model
{
    protected $fillable = [
        'tribunal_case_id',
        'user_id',
        'role',
    ];

    protected $casts = [
        'role' => TribunalPartyRole::class,
    ];

    public function tribunalCase(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
