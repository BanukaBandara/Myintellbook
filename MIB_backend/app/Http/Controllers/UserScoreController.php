<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HipRankMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserScoreController extends Controller
{
    /**
     * The signed-in user's Life Competency Index, its breakdown and their HIP rank.
     */
    public function lci(Request $request): JsonResponse
    {
        $user = User::query()->findOrFail($request->user()->getAuthIdentifier());

        return response()->json([
            'success' => true,
            ...HipRankMatrix::forUser($user),
        ]);
    }
}
