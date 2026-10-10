<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    /**
     * Dedicated Super Admin Login.
     *
     * Only authenticates accounts with is_admin = true.
     * Normal users and Jury Panel users are rejected.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'status' => false,
                'message' => 'Please provide a valid email and password.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'code' => 401,
                'status' => false,
                'message' => 'Invalid admin credentials.',
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'code' => 403,
                'status' => false,
                'message' => 'Access denied. Administrator privileges required.',
            ], 403);
        }

        $apiToken = new ApiToken();
        $token = $apiToken->tokenGenerate($user);

        return response()->json([
            'code' => 200,
            'status' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name ?? $user->email,
                'email' => $user->email,
                'is_admin' => true,
            ],
            'message' => 'Admin authenticated successfully',
        ], 200);
    }

    /**
     * Return authenticated Super Admin profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'code' => 403,
                'status' => false,
                'message' => 'Access denied. Administrator privileges required.',
            ], 403);
        }

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name ?? $user->email,
                'email' => $user->email,
                'is_admin' => true,
            ],
        ], 200);
    }

    /**
     * Revoke admin session / token.
     */
    public function logout(Request $request): JsonResponse
    {
        $authHeader = $request->header('Authorization');

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $rawToken = substr($authHeader, 7);
            $hashed = hash('sha256', $rawToken);
            ApiToken::where('token', $hashed)->delete();
        }

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Admin logged out successfully',
        ], 200);
    }
}
