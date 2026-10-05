<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WorkExperiance;
use Carbon\Carbon;
use App\Events\ScoreEvent;
use App\Notifications\NewUserNotification;

class CalculateExperienceScore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-experience-score';

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
        $experiences = WorkExperiance::get();

       foreach($experiences as $exp)
       {
           $start = Carbon::parse($exp->starting_date);
           $this->info($start);

           $end = $exp->currently_working
                ? Carbon::now()
                : Carbon::parse($exp->end_date);
            $this->info($end);

            $years = $start->diffInYears($end);
            $this->info($exp->id);
            $user = $exp->user;

            if($exp->positionType = 'executive')
            {
                ScoreEvent::dispatch(5,$exp->user_id,WorkExperiance::class,$exp->id,$years);
            }else{
                 ScoreEvent::dispatch(6,$user->id,WorkExperiance::class,$exp->id,$years);
            }

            $score = $exp->score;
            $user->notify(new NewUserNotification( $user->first_name."Your currently working experience have gain a ".$score->calculated_score." score to your  Life Competency Index(LCI) "));

       }
    }
}
