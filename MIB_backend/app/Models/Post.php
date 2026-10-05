<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Post extends Model
{
    protected $fillable = ['content','posting_date','user_id','post_image','question_id'];

    public function user()
    {
         return $this->belongsTo(User::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    protected $casts = [
    'content' => 'array',
    ];
}
