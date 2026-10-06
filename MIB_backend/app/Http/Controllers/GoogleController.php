<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Client as GoogleClient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class GoogleController extends Controller
{

    public function callback(Request $request)
    {
        try{
            $client = new GoogleClient();
            $client->setClientId(config('services.google.client_id'));
            $payload = $client->verifyIdToken($request->token);

        if ($payload) {
            $googleId = $payload['sub']; // Google unique ID
            $email    = $payload['email'];
            $name     = $payload['name'];

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'      => $name,
                    'google_id' => $googleId,
                    'password'  => bcrypt(str()->random(16)),
                ]
            );
            Auth::login($user);

             $apiToken = new \App\Models\ApiToken();
            $token = $apiToken->tokenGenerate($user);

            return response()->json([
                'code' => 200,
                'token' => $token,
                'user'  => $user,
            ]);
        }

        return response()->json(['error' => 'Invalid Google token'], 401);

        }catch(\Exception $e){
            return response()->json(['error'=>$e], 500);
    }
}
}
