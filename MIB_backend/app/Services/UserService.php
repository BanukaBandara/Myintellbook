<?php

namespace App\Services;

use App\Models\User;
use App\Models\ApiToken;
use App\Models\PasswordResetToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Jobs\PasswordResetTokenJob;
use App\Jobs\RemoveVerificationToken;


class UserService{

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

            $user = User::where('email', $request['email'])->first();

            if(!$user || !\Hash::check($request['password'], $user->password)) {
                throw new \Exception('Invalid credentials');
            }

            $apiToken = new \App\Models\ApiToken();
            $token = $apiToken->tokenGenerate($user);
            return response()->json([
                'code' => 200,
                'status' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'is_admin' => (bool) $user->is_admin,
                    'is_jury_panel' => $user->isJuryPanelAccount(),
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
        try{
            $user = User::where('email', $link['email'])->first();
            if(!$user) {
                throw new \Exception('User not found');
            }   
            $existingToken = PasswordResetToken::where('email', $link['email'])->first();
            // $existingToken->forceDelete();

            if($existingToken) {
                $existingToken->forceDelete();
            }
            $resetLink = PasswordResetToken::create([
                'email' => $link['email'],
                'token' => Str::random(60),
                'created_at' => now(),
            ]);
            $resetLink->sendPasswordResetEmail($link['email'], $resetLink->token);
            if(!$resetLink) {
                throw new \Exception('Password reset link failed');
            }
            if($resetLink) {
               
                PasswordResetTokenJob::dispatch($resetLink->email)->delay(now()->addMinutes(60));
            }
           
            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'Password reset link sent successfully',
            ], 200);
        }catch(\Exception $e){
            log::error('UserService @passwordResetLink: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function passwordReset($request, $token){

        try{
            $passwordResetToken = PasswordResetToken::where('token', $token)->first();
            if(!$passwordResetToken) {
                throw new \Exception('Invalid token');
            }
            $user = User::where('email', $passwordResetToken->email)->first();
            if(!$user) {
                throw new \Exception('User not found');
            }
            $user->password = $request['password'];
            $user->save();
            $passwordResetToken->where('token', $token)->forceDelete();
            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'Password reset successfully',
            ], 200);
        }catch(\Exception $e){
            log::error('UserService @passwordReset: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function userCheck($request){
        try{
            $user = Auth::user();
            if(!$user) {
                throw new \Exception('User not authenticated');
            }
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
                ],
                'message' => 'User authenticated successfully',
            ], 200);
        }catch(\Exception $e){
            log::error('UserService @userCheck: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
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
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}