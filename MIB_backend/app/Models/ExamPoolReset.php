<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamPoolReset extends Model
{
    protected $fillable = ['user_id', 'category_id', 'reset_at', 'pool_size'];

    protected $casts = [
        'category_id' => 'integer',
        'reset_at' => 'datetime',
        'pool_size' => 'integer',
    ];
}
