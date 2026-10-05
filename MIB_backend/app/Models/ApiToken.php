<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;


class ApiToken extends Model
{
    protected $fillable = ['user_id', 'token', 'expires_at'];

    public function tokenGenerate($user){
        $token = Str::random(80);
        $this->user_id = $user->id;
        $this->expires_at = Carbon::now()->addDays(intVal(config('token.expires_at')));
        $this->token = hash('sha256', $token);
        $this->save();
        return $token;
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
