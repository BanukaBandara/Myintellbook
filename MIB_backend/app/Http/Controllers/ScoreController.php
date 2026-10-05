<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Score;
use App\Models\Post;
use App\Models\Question;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Notifications\NewUserNotification;


class ScoreController extends Controller
{
    private $scoreService;

    public function __construct()
    {
        $this->scoreService = new \App\Services\ScoreFetchService();
    }

    public function getScores(Request $request)
    {
        return response()->json(['data'=>$this->scoreService->getAllScores(auth()->user()->id),'code'=>200]);
    }

    // public function topScores()
    // { 
    //     return response()->json(['data'=>$this->scoreService->getTopScores(),'code'=>200]); 
    // }
}
