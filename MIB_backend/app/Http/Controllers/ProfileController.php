<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\ProfileRequest;
use App\Http\Requests\GeneralInfoRequest;
use App\Http\Requests\WorkExperianceRequest;
use App\Http\Requests\EducationRequest;
use App\Models\User;
use App\Services\HipScoreCalculator;
use Auth;
use Illuminate\Support\Facades\Schema;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->profileService = new \App\Services\ProfileService();
        $this->searchService = new \App\Services\SearchService();
    }
    public function insert(ProfileRequest $request){
            $profile = $this->profileService->insertProfile($request->validated());
            return $profile;
    }

    public function userData(Request $request)
    {
        $user = $this->profileService->getUserData(Auth::user()->id);
        return $user;
    }

    public function userProfile()
    {
        $user = Auth::user();
        $user->load('profile');
        $user->hip_score = HipScoreCalculator::recalculate($user);

        return response()->json([
            'user' => $user,
            'hip_score' => $user->hip_score,
        ]);
    }

    public function show(string $id)
    {
        $userId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($userId === false) {
            return response()->json(['message' => 'User profile not found'], 404);
        }

        $user = User::with(['profile', 'educations', 'workExperiances', 'skills'])
            ->whereHas('profile')
            ->find($userId);
        if ($user === null) {
            return response()->json(['message' => 'User profile not found'], 404);
        }

        $achievements = collect();
        if (Schema::hasTable('achievements')) {
            $achievements = $user->achievements()
                ->where('verification_status', 'verified')
                ->get(['id', 'title', 'category'])
                ->map(fn ($achievement) => [
                    'id' => $achievement->id,
                    'title' => $achievement->title,
                    'category' => $achievement->category,
                ]);
        }

        $hipScore = HipScoreCalculator::recalculate($user);
        $profile = $user->profile;
        $latestExperience = $user->workExperiances
            ->sortByDesc('currently_working')
            ->sortByDesc('updated_at')
            ->first();
        $visibility = formatvisibility($user->getSettings());
        $profileDetails = [
            'id' => $user->id,
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'full_name' => $profile->full_name,
            'gender' => $profile->gender,
            'profile_image' => $profile->profile_image ?? '',
            'cover_image' => $profile->cover_image ?? '',
            'total_points' => $user->total_points,
            'hip_score' => $hipScore,
            'rank' => $user->Rank,
            'school' => $user->educations->first()?->school ?? '',
            'profession' => [
                'company' => $latestExperience?->company ?? '',
                'location' => $latestExperience?->location ?? '',
                'profession' => $latestExperience?->title ?? '',
            ],
            'experiance' => $user->workExperiances->map(fn ($experience) => [
                'id' => $experience->id,
                'title' => $experience->title,
                'company' => $experience->company,
                'currently_working' => $experience->currently_working,
                'location' => $experience->location,
                'selectEmpType' => $experience->selectEmpType,
                'locationType' => $experience->locationType,
                'starting_date' => $experience->starting_date,
                'end_date' => $experience->end_date,
                'positionType' => $experience->positionType,
            ]),
            'education' => $user->educations->map(fn ($education) => [
                'id' => $education->id,
                'school' => $education->school,
                'degree' => $education->degree,
                'field_of_study' => $education->field_of_study,
                'category' => $education->category,
            ]),
            'completed_exams' => [],
            'upcomming_exams' => [],
            'skills' => [
                'licensed' => $user->skills->where('type', 0)->values()->map(fn ($skill) => [
                    'id' => $skill->id,
                    'skill' => $skill->skill,
                ]),
                'vocational' => $user->skills->where('type', 1)->values()->map(fn ($skill) => [
                    'id' => $skill->id,
                    'skill' => $skill->skill,
                ]),
            ],
            'visibility' => $visibility + ['birth_date' => 'Private'],
            'profile_url' => $profile->full_url,
            'achievements' => $achievements,
        ];

        return response()->json([
            'status' => 'success',
            'user' => $profileDetails,
        ]);
    }

    public function editGeneralInfo(GeneralInfoRequest $request)
    {
        $user = $this->profileService->editGeneralInfo($request);
        return $user;
    }

    public function gtGeneralInfo()
    {
        $user = $this->profileService->getGeneralInfo();
        return $user;
    }

    public function addWorkExperiance(WorkExperianceRequest $request)
    {
        $user = $this->profileService->addWorkExperiance($request);
        return $user;
    }

    public function getExperiances($userSlug)
    {
        $user = $this->profileService->getExperiances($userSlug);
        return $user;
    }

    public function getExperianceDetails($id)
    {
        $user = $this->profileService->getExperianceDetails($id);
        return $user;
    }

    public function editExperianceDetails(WorkExperianceRequest $request)
    {
        $user = $this->profileService->editExperianceDetails($request);
        return $user;
    }

    public function deleteExperiance($id)
    {
        $user = $this->profileService->deleteExperiance($id);
        return $user;
    }

    public function addEducation(EducationRequest $request)
    {
        $user= $this->profileService->addEducation($request);
        return $user;
    } 

    public function getEducationDetails($userSlug)
    {
        $user= $this->profileService->getEducationDetails($userSlug);
        return $user;
    }

    public function getEducationDetail($id)
    {
        $user= $this->profileService->getEducationDetail($id);
        return $user;
    }

    public function deleteEducation($id)
    {
        $user = $this->profileService->deleteEducation($id);
        return $user;
    }

    public function editEducation(EducationRequest $request)
    {
        $user = $this->profileService->editEducation($request);
        return $user;
    }

    public function addSkill(Request $request)
    {
        $user = $this->profileService->addSkill($request);
        return $user;
    }

    public function getSkills()
    {
        $user = $this->profileService->getSkills();
        return $user;
    }

    public function deleteSkill($id)
    {
        $user = $this->profileService->deleteSkill($id);
        return $user;
    }

    public function uploadProfileImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'string', 'starts_with:data:image', 'max:5000000'],
        ]);

        $user = $this->profileService->uploadProfileImage($request);
        return $user;
    }

    public function uploadCoverImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'string', 'starts_with:data:image', 'max:5000000'],
        ]);

        $user = $this->profileService->uploadCoverImage($request);
        return $user;
    }

    public function checkProfileCompleted()
    {
         $user = $this->profileService->checkProfileCompleted();
        return $user;
    }

    public function basicInfo()
    {
        $user = $this->profileService->basicInfo();
        return $user;
    }

    public function profileList()
    {
        $user = $this->profileService->profileList();
        return $user;
    }

    public function search(Request $request)
    {
        $user = $this->searchService->searchKey($request->key);
        return $user;
    }

    public function profileListPaginated($page)
    {
        $user = $this->profileService->profileListPaginated($page);
        return $user;
    }

    public function getOtherProfileInfomations($slug)
    {
        $user = $this->profileService->getOtherProfileInfomations($slug);
        return $user;
    }

    public function submitComplains(Request $request){
        $user = $this->profileService->submitComplains($request);
        return $user;
    }

    public function getComplains($status)
    {
         $user = $this->profileService->getComplains($status);
        return $user;
    }
}
