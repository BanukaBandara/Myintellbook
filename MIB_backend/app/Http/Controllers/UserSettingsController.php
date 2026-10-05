<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserSettingsController extends Controller
{
    public function setSettings(Request $request)
    {
       try{
             $user = auth()->user();
         foreach ($request->all() as $key => $value) {
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
                'message' => $e->getMessage(),
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
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
