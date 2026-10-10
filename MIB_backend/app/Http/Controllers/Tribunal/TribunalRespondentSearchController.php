<?php

namespace App\Http\Controllers\Tribunal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tribunal\TribunalRespondentSearchResource;
use App\Services\Tribunal\TribunalRespondentSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TribunalRespondentSearchController extends Controller
{
    public function __construct(
        private readonly TribunalRespondentSearchService $searchService
    ) {
    }

    /**
     * Search eligible platform users for naming as case respondent.
     *
     * GET /api/tribunal/respondents/search?q={query}
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->input('q', '');
        $limit = (int) $request->input('limit', TribunalRespondentSearchService::DEFAULT_SEARCH_LIMIT);

        // Require minimum character length to prevent user enumeration
        if (mb_strlen(trim($query)) < TribunalRespondentSearchService::MIN_QUERY_LENGTH) {
            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => sprintf(
                    'Enter at least %d characters to search.',
                    TribunalRespondentSearchService::MIN_QUERY_LENGTH
                ),
                'data' => [],
            ], 200);
        }

        $results = $this->searchService->search(
            $query,
            (int) auth()->id(),
            $limit
        );

        return response()->json([
            'code' => 200,
            'status' => true,
            'data' => TribunalRespondentSearchResource::collection($results),
        ], 200);
    }
}
