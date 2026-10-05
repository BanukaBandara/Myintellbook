<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkExperianceRequest;
use App\Services\HipScoreCalculator;
use App\Services\ProfileService;

class ExperienceController extends Controller
{
    public function __construct(private readonly ProfileService $profileService) {}

    public function store(WorkExperianceRequest $request)
    {
        $response = $this->profileService->addWorkExperiance($request);
        HipScoreCalculator::recalculate(auth()->user());

        return $response;
    }

    public function update(WorkExperianceRequest $request)
    {
        $response = $this->profileService->editExperianceDetails($request);
        HipScoreCalculator::recalculate(auth()->user());

        return $response;
    }

    public function destroy(int $id)
    {
        $response = $this->profileService->deleteExperiance($id);
        HipScoreCalculator::recalculate(auth()->user());

        return $response;
    }

    public function index(string $userSlug)
    {
        return $this->profileService->getExperiances($userSlug);
    }

    public function show(int $id)
    {
        return $this->profileService->getExperianceDetails($id);
    }
}
