<?php

namespace App\Models;

use App\Enums\TribunalDeliberationNoteType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalDeliberationNote extends Model
{
    protected $fillable = [
        'tribunal_deliberation_id',
        'author_user_id',
        'note_type',
        'body',
    ];

    protected $casts = [
        'note_type' => TribunalDeliberationNoteType::class,
    ];

    public function deliberation(): BelongsTo
    {
        return $this->belongsTo(TribunalDeliberation::class, 'tribunal_deliberation_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
