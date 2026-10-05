<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    protected $fillable = ['school','degree','field_of_study','category'];

    public function score()
    {
        return $this->morphOne(UserScore::class, 'source');
    }
    
}