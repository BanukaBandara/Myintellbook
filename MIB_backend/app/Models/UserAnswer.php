<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAnswer extends Model
{
    public const STATUS_PENDING = 'pending_evaluation';
    public const STATUS_EVALUATED = 'evaluated';

    protected $fillable = [
        'user_id',
        'question_id',
        'selected_option_index',
        'answer_date',
        'score',
        'status',
        'is_correct',
        'evaluated_at',
    ];

    protected $casts = [
        'selected_option_index' => 'integer',
        'answer_date' => 'date',
        'score' => 'float',
        'is_correct' => 'boolean',
        'evaluated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
