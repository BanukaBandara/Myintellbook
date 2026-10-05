<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Professional\RejectProfessionalVerificationRequest;
use App\Http\Requests\Professional\SuspendProfessionalVerificationRequest;
use App\Http\Resources\Professional\ProfessionalVerificationResource;
use App\Models\ProfessionalVerification;
use App\Services\Professional\ProfessionalVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminProfessionalVerificationController extends Controller
{
    public function __construct(
        protected ProfessionalVerificationService $verificationService
    ) {}

    /**
     * Check that current user is an administrator.
     */
    protected function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403, 'Access denied. Administrator privileges required.');
    }

    /**
     * List all professional verification applications.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = ProfessionalVerification::query()
            ->with(['user.profile', 'adjudicatorProfile'])
            ->latest('submitted_at');

        if ($request->filled('status')) {
            $query->where('verification_status', $request->input('status'));
        }

        if ($request->filled('profession_type')) {
            $query->where('profession_type', $request->input('profession_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('issuing_authority', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%")
                  ->orWhere('enrollment_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('email', 'like', "%{$search}%")
                         ->orWhereHas('profile', function ($pq) use ($search) {
                             $pq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                         });
                  });
            });
        }

        $verifications = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => ProfessionalVerificationResource::collection($verifications),
            'meta' => [
                'current_page' => $verifications->currentPage(),
                'last_page' => $verifications->lastPage(),
                'per_page' => $verifications->perPage(),
                'total' => $verifications->total(),
            ],
        ]);
    }

    /**
     * View details of a specific professional verification.
     */
    public function show(Request $request, ProfessionalVerification $verification): JsonResponse
    {
        $this->authorizeAdmin($request);

        $verification->load(['user.profile', 'adjudicatorProfile', 'events']);

        return response()->json([
            'data' => new ProfessionalVerificationResource($verification),
        ]);
    }

    /**
     * Approve a verification application.
     */
    public function approve(Request $request, ProfessionalVerification $verification): JsonResponse
    {
        $this->authorizeAdmin($request);

        $approved = $this->verificationService->approve($verification, $request->user());

        return response()->json([
            'message' => 'Professional verification approved successfully.',
            'data' => new ProfessionalVerificationResource($approved->load(['user.profile', 'adjudicatorProfile', 'events'])),
        ]);
    }

    /**
     * Reject a verification application.
     */
    public function reject(RejectProfessionalVerificationRequest $request, ProfessionalVerification $verification): JsonResponse
    {
        $this->authorizeAdmin($request);

        $rejected = $this->verificationService->reject(
            $verification,
            $request->user(),
            $request->validated('rejection_reason')
        );

        return response()->json([
            'message' => 'Professional verification rejected.',
            'data' => new ProfessionalVerificationResource($rejected->load(['user.profile', 'adjudicatorProfile', 'events'])),
        ]);
    }

    /**
     * Suspend a verified professional.
     */
    public function suspend(SuspendProfessionalVerificationRequest $request, ProfessionalVerification $verification): JsonResponse
    {
        $this->authorizeAdmin($request);

        $suspended = $this->verificationService->suspend(
            $verification,
            $request->user(),
            $request->validated('suspension_reason')
        );

        return response()->json([
            'message' => 'Professional verification suspended.',
            'data' => new ProfessionalVerificationResource($suspended->load(['user.profile', 'adjudicatorProfile', 'events'])),
        ]);
    }

    /**
     * Stream a private credential document to an administrator.
     */
    public function downloadDocument(Request $request, ProfessionalVerification $verification, string $documentType): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeAdmin($request);

        return $this->verificationService->downloadDocument($verification, $documentType, $request->user());
    }
}
