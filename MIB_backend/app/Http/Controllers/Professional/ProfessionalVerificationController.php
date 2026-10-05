<?php

namespace App\Http\Controllers\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\Professional\ApplyProfessionalVerificationRequest;
use App\Http\Resources\Professional\ProfessionalVerificationResource;
use App\Models\ProfessionalVerification;
use App\Services\Professional\ProfessionalVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfessionalVerificationController extends Controller
{
    public function __construct(
        protected ProfessionalVerificationService $verificationService
    ) {}

    /**
     * Submit a professional verification application.
     */
    public function apply(ApplyProfessionalVerificationRequest $request): JsonResponse
    {
        $files = [
            'qualification_document' => $request->file('qualification_document'),
            'identity_document' => $request->file('identity_document'),
            'additional_document' => $request->file('additional_document'),
        ];

        $verification = $this->verificationService->apply(
            $request->user(),
            $request->validated(),
            $files
        );

        return response()->json([
            'message' => 'Professional verification application submitted successfully.',
            'data' => new ProfessionalVerificationResource($verification->load(['user.profile', 'adjudicatorProfile'])),
        ], 201);
    }

    /**
     * Get current user's professional verification details and status.
     */
    public function myVerification(Request $request): JsonResponse
    {
        $verification = ProfessionalVerification::where('user_id', $request->user()->id)
            ->with(['user.profile', 'adjudicatorProfile'])
            ->first();

        if (!$verification) {
            return response()->json([
                'has_application' => false,
                'data' => null,
            ], 200);
        }

        return response()->json([
            'has_application' => true,
            'data' => new ProfessionalVerificationResource($verification),
        ], 200);
    }
}
