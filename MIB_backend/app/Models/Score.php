<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    protected $fillable = [
        'user_id',
        'question_id',
        'activity_id', 
        'activity_type',
        'base_type', // 0: daily_questions, 1: profile_update, 2: exam_questions
        'points',
        'date',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class)->with('profile');
    }

    public function question()
    {
        return $this->belongsTo(\App\Models\Question::class);
    }


    public function activity()
    {
        return $this->morphTo();
    }
}
