<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationsController extends Controller
{
    public function getNotifications()
    {
        $notifications = auth()->user()->notifications()->latest()->take(5)->get();

       $formated=$notifications->map(function($notification){
            return[
                'id'=>$notification->id,
                'message'=>$notification->data['message'],
                'isRead' => ($notification->read_at == null) ?false : true,
                'created_at'=>\Carbon\Carbon::parse($notification->created_at)->diffForHumans(),
                'profile_image'=>auth()->user()->profile->profile_image,
                'user_name'=>auth()->user()->profile->first_name
            ];
       });

        return response()->json([
            'data'=>$formated,
            'code' => 200,
            'status' => true,
        ],200);
    }

    public function getAll(){

        $notifications = auth()->user()->notifications;

       $formated=$notifications->map(function($notification){
            return[
                'id'=>$notification->id,
                'message'=>$notification->data['message'],
                'isRead' => ($notification->read_at == null) ?false : true,
                'created_at'=>\Carbon\Carbon::parse($notification->created_at)->diffForHumans(),
                'profile_image'=>auth()->user()->profile->profile_image,
                'user_name'=>auth()->user()->profile->first_name
            ];
       });

        return response()->json([
            'data'=>$formated,
            'code' => 200,
            'status' => true,
        ],200);
    }
}
