<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Mail;
use App\Mail\passwordReset;

class PasswordResetToken extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'password_reset_tokens';
    protected $dates = ['deleted_at'];
    protected $primaryKey = 'email';
    public $incrementing = false; // Important because email is not an integer
    protected $keyType = 'string'; //email is a string so i have to mention it on the primary key otherwise it will return 0

    protected $fillable = [
        'email',
        'token',
        'created_at',
        'deleted_at'
    ];

    public function sendPasswordResetEmail($email,$token)
    {
        $verificationUrl = config('app.password_reset_link').":". $token;
        Mail::to($email)->send(new passwordReset($verificationUrl));
    }
}
