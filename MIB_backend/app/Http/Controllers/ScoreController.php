<?php

namespace App\Http\Controllers;

use App\Services\ScoreFetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScoreController extends Controller
{
    public function __construct(private ScoreFetchService $scoreService)
    {
    }

    /**
     * The signed-in user's scored records, grouped by Score page tab.
     */
    public function getScores(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->scoreService->getAllScores($request->user()->getAuthIdentifier()),
            'code' => 200,
        ]);
    }
}
