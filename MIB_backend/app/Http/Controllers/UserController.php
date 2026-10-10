<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Http\Requests\ChnagePasswordRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class UserController extends Controller
{

    public function __construct()
    {
       $this->userService = new \App\Services\UserService();
    }
    public function userRegister(RegisterRequest $request){
        $user = $this->userService->registerUser($request->validated());
        return $user;
    }

    public function verifyEmail(Request $request){
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:100'],
        ]);
        $user = $this->userService->verifyEmail($validated['token']);
        return $user;
    }

    public function resendVerificationEmail(Request $request)
    {
        return $this->userService->resendVerificationEmail($request->user());
    }
    public function userLogin(LoginRequest $request){
        $user = $this->userService->loginUser($request->validated());
        return $user;
    }

    public function passwordResetLink(PasswordResetRequest $request){
        $user = $this->userService->passwordResetLink($request->validated());
        return $user;
    }

    public function passwordReset(ChnagePasswordRequest $request, $token){
        $user = $this->userService->passwordReset($request->validated(), $token);
        return $user;
    }

    public function userCheck(Request $request){
      
        $user = $this->userService->userCheck($request);
        return $user;

    }

    public function logOut()
    {
        $user= $this->userService->logOut();
        return $user;
    }

    public function DeleteUser()
    {
        $user  = User::find(auth()->user()->id);

        $user->posts->delete();
        

        $user->delete();

         return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'Successfully deleted',
            ], 200);
    }
}
