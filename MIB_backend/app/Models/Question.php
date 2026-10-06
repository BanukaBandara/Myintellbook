<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\profession;

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
        'difficulty_level',
        'profession_id',
    ];
    
    protected $casts = [
        'options' => 'array',
        'is_used' => 'boolean',
    ];

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
