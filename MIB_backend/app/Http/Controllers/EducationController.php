<?php

namespace App\Http\Controllers;

use App\Http\Requests\EducationRequest;
use App\Services\HipScoreCalculator;
use App\Services\ProfileService;

class EducationController extends Controller
{
    public function __construct(private readonly ProfileService $profileService) {}

    public function store(EducationRequest $request)
    {
        $response = $this->profileService->addEducation($request);
        HipScoreCalculator::recalculate(auth()->user());

        return $response;
    }

    public function update(EducationRequest $request)
    {
        $response = $this->profileService->editEducation($request);
        HipScoreCalculator::recalculate(auth()->user());

        return $response;
    }

    public function destroy(int $id)
    {
        $response = $this->profileService->deleteEducation($id);
        HipScoreCalculator::recalculate(auth()->user());

        return $response;
    }

    public function index(string $userSlug)
    {
        return $this->profileService->getEducationDetails($userSlug);
    }

    public function show(int $id)
    {
        return $this->profileService->getEducationDetail($id);
    }
}
