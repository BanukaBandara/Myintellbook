<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Score;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class CalculateUserRanks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-user-ranks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $points = Score::with('user')->select('user_id', DB::raw('SUM(points) as total_points'))
                    ->whereHas('user.profile', function ($q) {
                        $q->whereNotNull('first_name');
                        })
                    ->groupBy('user_id')
                    ->orderBy('total_points', 'desc')
                    ->get()
                    ->toArray();

        $rank = 0;
        $lastPoints = null;
        foreach ($points as $index => $point) {
           
            $user = \App\Models\User::find($point['user_id']);
            if ($user && ($point['total_points'] == $lastPoints)) {
                $user->Rank = $rank;
                $user->total_points = $point['total_points'];
                $user->save();  
            }else{
                $rank++;
                $user->Rank = $rank;
                $user->total_points = $point['total_points'];
                $user->save(); 
            }
            
            $lastPoints = $point['total_points'];
        }

        $ranks = User::whereNotNull('rank')->orderBy('rank','asc')->get();
        foreach($ranks as $rank){
            \Illuminate\Support\Facades\Log::info("User ID: {$rank->id}, Rank: {$rank->rank}, Total Points: {$rank->total_points}");

            if($this->checkNotification_exsists($rank)) return;
            
            if($rank->rank <= 3){
                $rank->notify(new \App\Notifications\NewUserNotification("Congratulations! You are in the Top 3 ranks with rank {$rank->rank}. Keep up the great work!"));
            }else{
                $rank->notify(new \App\Notifications\NewUserNotification("Your current rank is {$rank->rank} with total points {$rank->total_points}. Keep up the good work!"));
            }
           
        }
        
    }
    public function checkNotification_exsists($user){

            $exists = $user->notifications()
            ->where('type', \App\Notifications\NewUserNotification::class)
            ->where('data->message', "Congratulations! You are in the Top 3 ranks with rank {$user->rank}. Keep up the great work!")
            ->orWhere('data->message', "Your current rank is {$user->rank} with total points {$user->total_points}. Keep up the good work!")
            ->exists();

            return $exists;

        }
}
