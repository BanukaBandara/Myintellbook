<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Complain extends Model
{
    protected $fillable=['user_id','complain','category','status','from','complainer'];

     public function user()
    {
        return $this->belongsTo(User::class);
                
    }

    public function complainerUser()
    {
        return $this->belongsTo(User::class,'complainer','id');
                
    }
}
