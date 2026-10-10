<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserSettingsRequest;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserSettingsController extends Controller
{
    public function setSettings(UserSettingsRequest $request)
    {
       try{
             $user = auth()->user();
         // Only allowlisted visibility keys with known values (see UserSettingsRequest).
         foreach ($request->settings() as $key => $value) {
            $user->setSetting($key, $value);
        }

        return response()->json([
                'code' => 200,
                'status' => true,
                'data' => 'Successfully inserted settings',
            ], 200);
       }catch(\Exception $e){
        log::error('UserSettingsController @setSettings: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }

    public function getSettings()
    {
         try{
            $settings = auth()->user()->getSettings();


            $result = [];

            foreach ($settings as $item) {
                $result[$item['Key']] = ['name'=>$item['value']];
            }

        return response()->json([
                'code' => 200,
                'status' => true,
                'data' => $result,
            ], 200);
       }catch(\Exception $e){
        log::error('UserSettingsController @setSettings: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }
}
