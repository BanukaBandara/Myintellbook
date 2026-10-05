<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Score;
use Carbon\Carbon;

class WorkExperiance extends Model
{
    protected $fillable =['title','company','currently_working','location','selectEmpType','locationType','starting_date','end_date','positionType'];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

     public function scores()
    {
        return $this->morphMany(Score::class, 'activity');
    }
    

    public function getDiffrenceAttribute(){

        $date1 = Carbon::parse($this->starting_date);
        $date2 = Carbon::parse($this->end_date);

        $days = $date1->diffInYears($date2);

        return $days;

    }

    public function score()
    {
        return $this->morphOne(UserScore::class, 'source');
    }
}
