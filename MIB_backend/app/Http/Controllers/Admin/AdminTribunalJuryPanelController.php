<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTribunalJuryPanelRequest;
use App\Http\Requests\Admin\UpdateTribunalJuryPanelRequest;
use App\Http\Resources\Admin\TribunalJuryPanelResource;
use App\Models\TribunalJuryPanel;
use App\Services\Tribunal\TribunalJuryPanelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTribunalJuryPanelController extends Controller
{
    public function __construct(
        protected TribunalJuryPanelService $juryPanelService
    ) {}

    /**
     * Check that current user is an administrator.
     */
    protected function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403, 'Access denied. Administrator privileges required.');
    }

    /**
     * List all Jury Panels.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $panels = $this->juryPanelService->getPanels(
            $request->input('search'),
            $request->input('status'),
            (int) $request->input('per_page', 15)
        );

        return response()->json([
            'data' => TribunalJuryPanelResource::collection($panels),
            'meta' => [
                'current_page' => $panels->currentPage(),
                'last_page' => $panels->lastPage(),
                'per_page' => $panels->perPage(),
                'total' => $panels->total(),
            ],
        ]);
    }

    /**
     * Create a new Jury Panel with a dedicated User account.
     */
    public function store(CreateTribunalJuryPanelRequest $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $juryPanel = $this->juryPanelService->createPanel(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Jury panel created successfully.',
            'data' => new TribunalJuryPanelResource($juryPanel),
        ], 201);
    }

    /**
     * View details of a specific Jury Panel.
     */
    public function show(Request $request, TribunalJuryPanel $juryPanel): JsonResponse
    {
        $this->authorizeAdmin($request);

        $juryPanel->load(['loginUser', 'creator', 'events.actor']);

        return response()->json([
            'data' => new TribunalJuryPanelResource($juryPanel),
        ]);
    }

    /**
     * Update an existing Jury Panel.
     */
    public function update(UpdateTribunalJuryPanelRequest $request, TribunalJuryPanel $juryPanel): JsonResponse
    {
        $this->authorizeAdmin($request);

        $updated = $this->juryPanelService->updatePanel(
            $juryPanel,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Jury panel updated successfully.',
            'data' => new TribunalJuryPanelResource($updated),
        ]);
    }

    /**
     * Activate a Jury Panel.
     */
    public function activate(Request $request, TribunalJuryPanel $juryPanel): JsonResponse
    {
        $this->authorizeAdmin($request);

        $activated = $this->juryPanelService->activatePanel($juryPanel, $request->user());

        return response()->json([
            'message' => 'Jury panel activated successfully.',
            'data' => new TribunalJuryPanelResource($activated),
        ]);
    }

    /**
     * Deactivate a Jury Panel.
     */
    public function deactivate(Request $request, TribunalJuryPanel $juryPanel): JsonResponse
    {
        $this->authorizeAdmin($request);

        $deactivated = $this->juryPanelService->deactivatePanel($juryPanel, $request->user());

        return response()->json([
            'message' => 'Jury panel deactivated successfully.',
            'data' => new TribunalJuryPanelResource($deactivated),
        ]);
    }
}
