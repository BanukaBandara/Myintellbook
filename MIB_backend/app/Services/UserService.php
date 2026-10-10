<?php

namespace App\Services;

use App\Models\User;
use App\Models\ApiToken;
use App\Models\PasswordResetToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use App\Jobs\PasswordResetTokenJob;
use App\Jobs\RemoveVerificationToken;


class UserService{

    /** Rate-limiter key for failed sign-ins on one email ('user' or 'admin' portal). */
    public static function loginFailureKey(string $portal, ?string $email): string
    {
        return "login-failures:{$portal}:".sha1(strtolower(trim((string) $email)));
    }

    public static function lockedOutResponse(string $lockoutKey)
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($lockoutKey) / 60));

        return response()->json([
            'code' => 429,
            'status' => false,
            'message' => "Too many failed sign-in attempts for this account. Try again in {$minutes} minute(s), or reset your password.",
        ], 429);
    }

    public function resendVerificationEmail(User $user)
    {
        if ($user->email_verified_at !== null) {
            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'Your email address is already verified.',
            ], 200);
        }

        try {
            $user->generateVerificationToken();
            RemoveVerificationToken::dispatch($user->id)->delay(now()->addMinutes(60));
        } catch (\Throwable $e) {
            Log::error('UserService @resendVerificationEmail failed', ['user_id' => $user->id, 'exception' => $e]);

            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'We could not send the verification email. Please try again later.',
            ], 500);
        }

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'A new verification email has been sent. The link is valid for 60 minutes.',
        ], 200);
    }

    public function registerUser($user){
        try{
            $user = User::create($user);
            if(!$user) {
                throw new \Exception('User registration failed');
            }
            $user->generateVerificationToken();
            $token = $user->id;
            if($token) {
                RemoveVerificationToken::dispatch($token)->delay(now()->addMinutes(60));
            }

            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'User registered successfully',
            ], 200);
        }catch(\Exception $e){
            log::error('UserService @registerUser: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'User registration failed',
            ], 500);
        }
    }

    public function verifyEmail($token){ 
        try{
            $user = User::where('email_verification_token', $token)->first();
            if(!$user) {
                throw new \Exception('Invalid token');
            }
            $user->email_verified_at = now();
            $user->email_verification_token = null;
            $user->save();
            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'Email verified successfully',
            ], 200);
        }catch(\Exception $e){
            log::error('UserService @verifyEmail: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Email verification failed',
            ]);
        }
    }
    public function loginUser($request){
        try{
            // Lock an email out after repeated wrong passwords, whichever IPs they come from.
            $lockoutKey = self::loginFailureKey('user', $request['email']);
            if (RateLimiter::tooManyAttempts($lockoutKey, (int) config('token.login_max_failures', 5))) {
                return self::lockedOutResponse($lockoutKey);
            }

            $user = User::where('email', $request['email'])->first();

            if(!$user || !\Hash::check($request['password'], $user->password)) {
                RateLimiter::hit($lockoutKey, (int) config('token.login_lockout_seconds', 900));

                return response()->json([
                    'code' => 401,
                    'status' => false,
                    'message' => 'Invalid email or password.',
                ], 401);
            }

            RateLimiter::clear($lockoutKey);

            if ($user->isAdmin()) {
                return response()->json([
                    'code' => 403,
                    'status' => false,
                    'requires_admin_portal' => true,
                    'message' => 'This administrator account must use the admin login portal.',
                ], 403);
            }

            // Internal Tribunal Account Suspension Check
            $suspensionService = app(\App\Services\InternalTribunal\AccountSuspensionService::class);
            $activeSuspension = $suspensionService->getActiveSuspension($user);
            if ($activeSuspension) {
                return response()->json($suspensionService->formatSuspensionPayload($activeSuspension), 403);
            }

            $apiToken = new \App\Models\ApiToken();
            $token = $apiToken->tokenGenerate($user);
            $user->load('profile');
            return response()->json([
                'code' => 200,
                'status' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'is_admin' => (bool) $user->is_admin,
                    'is_jury_panel' => $user->isJuryPanelAccount(),
                    'is_profile_completed' => $user->isProfileCompleted(),
                    'profile' => $user->profile,
                ],
                'message' => 'User logged in successfully',
            ], 200);
            
            
        }catch(\Exception $e){
            log::error('UserService @loginUser: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'User login failed',
            ], 500);
        }
    }

    public function passwordResetLink($link){
        // Same answer whether or not the email has an account, so this can't be used
        // to discover registered emails.
        $genericResponse = response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ], 200);

        try{
            $user = User::where('email', $link['email'])->first();
            if(!$user) {
                return $genericResponse;
            }

            PasswordResetToken::withTrashed()->where('email', $link['email'])->forceDelete();

            // Only a hash is stored; the raw token exists only in the emailed link.
            $rawToken = Str::random(64);
            $resetLink = PasswordResetToken::create([
                'email' => $link['email'],
                'token' => hash('sha256', $rawToken),
                'created_at' => now(),
            ]);
            $resetLink->sendPasswordResetEmail($link['email'], $rawToken);

            // Clean-up only; expiry is enforced in passwordReset() even if the queue isn't running.
            PasswordResetTokenJob::dispatch($resetLink->email)
                ->delay(now()->addMinutes((int) config('token.password_reset_expires_minutes', 60)));

            return $genericResponse;
        }catch(\Exception $e){
            log::error('UserService @passwordResetLink: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }
    public function passwordReset($request, $token){

        $invalidResponse = response()->json([
            'code' => 422,
            'status' => false,
            'message' => 'This password reset link is invalid or has expired. Please request a new one.',
        ], 422);

        try{
            $validFrom = now()->subMinutes((int) config('token.password_reset_expires_minutes', 60));
            $passwordResetToken = PasswordResetToken::where('token', hash('sha256', (string) $token))
                ->where('created_at', '>=', $validFrom)
                ->first();
            if(!$passwordResetToken) {
                return $invalidResponse;
            }
            $user = User::where('email', $passwordResetToken->email)->first();
            if(!$user) {
                return $invalidResponse;
            }

            DB::transaction(function () use ($user, $request, $passwordResetToken) {
                $user->password = $request['password'];
                $user->save();

                // Sign out every existing session: whoever triggered the reset may be
                // recovering from a stolen password or token.
                ApiToken::where('user_id', $user->id)->delete();

                PasswordResetToken::withTrashed()->where('email', $passwordResetToken->email)->forceDelete();
            });

            RateLimiter::clear(self::loginFailureKey('user', $user->email));

            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'Password reset successfully. Please sign in with your new password.',
            ], 200);
        }catch(\Exception $e){
            log::error('UserService @passwordReset: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }

    public function userCheck($request){
        try{
            $user = Auth::user();
            if(!$user) {
                throw new \Exception('User not authenticated');
            }
            $user->load('profile');
            return response()->json([
                'code' => 200,
                'status' => true,
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'is_admin' => (bool) $user->is_admin,
                    'is_jury_panel' => $user->isJuryPanelAccount(),
                    'Rank' => $user->Rank,
                    'total_points' => $user->total_points,
                    'is_profile_completed' => $user->isProfileCompleted(),
                    'profile' => $user->profile,
                ],
                'message' => 'User authenticated successfully',
            ], 200);
        }catch(\Exception $e){
            log::error('UserService @userCheck: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }

    public function logOut()
    {
        try{
            $tokens = ApiToken::with('user')->where('user_id',Auth::user()->id)->delete();
            // $tokens->delete();
            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'User log out  successfully',
            ], 200);
        }catch(\Exception $e)
        {
            log::error('UserService @logOut: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }
}