<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jury extends Model
{
    protected $fillable=['user_id','complain_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
                
    }
}
