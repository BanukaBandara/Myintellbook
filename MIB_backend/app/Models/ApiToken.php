<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;


class ApiToken extends Model
{
    protected $fillable = ['user_id', 'token', 'expires_at'];

    /**
     * @param int|null $lifetimeMinutes Shorter lifetime for privileged sessions (admin);
     *                                  defaults to token.expires_at days for regular users.
     */
    public function tokenGenerate($user, ?int $lifetimeMinutes = null){
        $token = Str::random(80);
        $this->user_id = $user->id;
        $this->expires_at = $lifetimeMinutes !== null
            ? Carbon::now()->addMinutes($lifetimeMinutes)
            : Carbon::now()->addDays(intVal(config('token.expires_at')));
        $this->token = hash('sha256', $token);
        $this->save();
        return $token;
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
