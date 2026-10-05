<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\profession;
use App\Services\DailyQuestionResolver;

class Question extends Model
{
    protected $fillable = [
        'category',
        'question',
        'options',
        'answer',
        'is_used',
        'user_id',
        'issue_date',
        'scheduled_date',
        'difficulty_level',
        'profession_id',
    ];
    
    protected $casts = [
        'options' => 'array',
        'is_used' => 'boolean',
        'scheduled_date' => 'date',
    ];

    /**
     * Questions that may be shown outside the Daily Question (Learn, Exam): excludes today's
     * daily question and anything scheduled as a future daily question.
     */
    public function scopeOutsideDailyRotation(Builder $query): Builder
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('scheduled_date')->orWhereDate('scheduled_date', '<', today());
        });

        if ($todayId = DailyQuestionResolver::today()?->id) {
            $query->whereKeyNot($todayId);
        }

        return $query;
    }

    public function profession()
    {
        return $this->belongsTo(profession::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
    public function usersSeen()
    {
        return $this->belongsToMany(User::class, 'question_user')->withTimestamps();
    }

    public function posts()
    {
        return $this->hasMany(\App\Models\Post::class, 'question_id');
    }

    public function exams()
    {
        return $this->belongsToMany(\App\Models\Exam::class,'exam_question');
    }

    public function QuesOptions()
    {
        return $this->hasMany(Option::class);
    }
}
