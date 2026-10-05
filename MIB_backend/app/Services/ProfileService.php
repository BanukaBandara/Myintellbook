<?php

namespace App\Services;
use App\Models\Profile;
use App\Models\WorkExperiance;
use App\Models\Education;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Post;
use App\Models\Complain;
use Carbon\Carbon;
use App\Models\Jury;
use App\Notifications\NewUserNotification;
use App\Events\ScoreEvent;
use Illuminate\Support\Str;

class ProfileService
{

    public function checkProfileCompleted()
    {
        $attributes = [
            'generalInfo'=>
            [
                'first_name' =>!empty(Auth::user()->profile['first_name']),
                'last_name'=>!empty(Auth::user()->profile['last_name']),
                'gender'=>!empty(Auth::user()->profile['gender']),
                'birth_date'=>!empty(Auth::user()->profile['birth_date']),
            ],
            'coverImage'=>Auth::user()->profile['cover_image'] != NULL,
            'profileImage'=>Auth::user()->profile['profile_image'] != NULL,
            'workExperiance' => WorkExperiance::where('user_id',Auth::user()->id)->exists(),
            'education'=> Education::where('user_id',Auth::user()->id)->exists(),
            'skills'=> Skill::where('user_id',Auth::user()->id)->exists(),
        ];
        return response()->json([
                'code' => 200,
                'status' => true,
                'data' => $attributes,
        ], 200);

    }

    public function basicInfo()
    {

       try{
                $today = Carbon::today()->toDateString();

                $profileDetails = User::with(['workExperiances' => function ($query) {
                        $query->where('currently_working', 1);
                }])->with(['question'=>function($query)use ($today) {
                    $query->where('user_id',Auth::user()->id)->whereDate('issue_date',$today);
                    $query->with('QuesOptions');
                }])->where('id',Auth::user()->id)->get();

                
                // $profileDetails = User::with(['workExperiances' => function ($query) {
                //         $query->where('currently_working', 1);
                // }])->with(['question'=>function($query)use ($today) {
                //     $query->where('user_id',Auth::user()->id)->whereDate('issue_date',$today);
                //     $query->with('QuesOptions');
                // }])->where('id',Auth::user()->id)->get();

                if(!userDataFormatting($profileDetails))
                {
                    throw new Exception('error getting basic infomations');
                }
                

            return userDataFormatting($profileDetails);

       }catch(\Exception $e){
         log::error('ProfileService @basicInfo: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
       }
    }
    public function insertProfile($request)
    {
        try{
            // Re-submitting onboarding updates the existing profile instead of creating a duplicate.
            $profile = Profile::firstOrNew(['user_id' => Auth::user()->id]);

            $profile->first_name= $request['first_name'];
            $profile->last_name= $request['last_name'];
            $profile->gender= $request['gender'];
            $profile->birth_date = $request['birth_date'];
            $profile->profession_id= $request['profession_id'];
            $profile->user_id = Auth::user()->id;
            $profile->save();

            if(!$profile){
                throw new \Exception('Profile not created');
            }

            return response()->json([
                'code' => 200,
                'status' => true,
                'data' => 'Profile created successfully',
            ], 200);
        }catch(\Exception $e){
            log::error('CategoryService @getCategories: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getUserData($user_id){
        try{
            $profile = Profile::where('user_id', $user_id)->firstOrFail();
            $user = $profile->user;
            $lci = \App\Services\HipRankMatrix::forUser($user);

            $user_summary =[
                'full_name'=> $profile->full_name,
                'profile_image'=> ($profile->profile_image) ? $profile->profile_image : '',
                'cover_image'=>($profile->cover_image) ? $profile->cover_image: '',
                'total_points'=>$user->total_points,
                'hip_score'=>$lci['lci_score'],
                'lci_score'=>$lci['lci_score'],
                'hip_rank'=>$lci['hip_rank'],
                'rank_tier'=>$lci['rank_tier'],
                'rank_badge_color'=>$lci['rank_badge_color'],
                'rank'=>$user->Rank,
                'school'=>getSchool($user->id),
                'profession'=>getProfession($user->id),
            ];
            return response()->json([
                'code' => 200,
                'status' => true,
                'data' => $user_summary,
            ], 200);
        }catch(\Exception $e){
            log::error('CategoryService @getCategories: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function editGeneralInfo($request)
    {
        try{
             
            $user = Profile::where('user_id',Auth::user()->id)->update([
                'first_name'=>$request['first_name'],
                'last_name'=>$request['last_name'],
                'gender'=>$request['gender'],
                'birth_date'=>$request['birth_date']
            ]);
            
            foreach ($request->visibility as $key => $value) {
                auth()->user()->setSetting($key, $value);
            }

            if(!$user){
                throw new \Exception('Data not inserted corrrectly');
            }
            return response()->json([
                'code' => 200,
                'status' => true,
                'data' => 'General Info Edit successfull',
            ], 200);

        }catch(\Exception $e)
        {
              log::error('ProfileService @editGeneralInfo: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getGeneralInfo()
    {
        try{
            $info =User::where('id',auth()->user()->id)->get();
            $formate_info = formatUserInfo($info);

            if(!$info)
            {
                throw new \Exception('general info not getting');
            }
             return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>$formate_info,
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @getGeneralInfo: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function addWorkExperiance($request)
    {
        try{
            if($request['currently_working']){
                    WorkExperiance::where('user_id', Auth::id())
                        ->where('currently_working', 1)
                        ->get()
                        ->each(function (WorkExperiance $experience): void {
                            $experience->currently_working = 0;
                            $experience->save();
                        });
            }

            $work = new WorkExperiance();
            $work->title = $request['title'];
            $work->company = $request['company'];
            $work->currently_working = $request['currently_working'];
            $work->location = $request['location'];
            $work->selectEmpType = $request['selectEmpType'];
            $work->locationType = $request['locationType'];
            $work->starting_date = $request['startingDate'];
            $work->end_date = $request['endDate'];
            $work->positionType = $request['position'];
            $work->user_id = Auth::id();
            $work->save();

            if(!$work)
            {
                throw new \Exception('work experiance not inserted correctly');
            }

             return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>'work experiance inserted successfully',
            ], 200);
        }catch(\Exception $e){
             log::error('ProfileService @addWorkExperiance: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getExperiances($userSlug){
        try{
            $uuid = Str::match(
                        '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
                        $userSlug
                    );

            $profileDetails = User::with('profile')->whereHas('profile', function($q)use($uuid) {
                    $q->where('uuid',$uuid);
                })->get();

            $experiance = WorkExperiance::where('user_id',$profileDetails[0]->id)->get();

            if(!$experiance)
            {
                throw new \Exception('work experiance not get correctly');
            }
             return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>$experiance,
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @getExperiances: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getExperianceDetails($id)
    {
         try{

            $experiance = WorkExperiance::where('id',$id)->where('user_id',Auth::user()->id)->get();

            if(!$experiance)
            {
                throw new \Exception('work experiance not get correctly');
            }
            if(count($experiance) == 0)
            {
                throw new \Exception('You have no authorized');
            }
             return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>$experiance,
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @getExperiances: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function editExperianceDetails($request)
    {
         try{
            if($request['currently_working']){
                     WorkExperiance::where('currently_working', 1)
                         ->where('user_id', Auth::id())
                         ->where('id', '!=', $request['id'])
                         ->get()
                         ->each(function (WorkExperiance $experience): void {
                             $experience->currently_working = 0;
                             $experience->save();
                         });
             }
             $experiance = WorkExperiance::where('id', $request['id'])
                 ->where('user_id', Auth::id())
                 ->firstOrFail();
             $experiance->fill([
                 'title' => $request['title'],
                 'company' => $request['company'],
                 'currently_working' => $request['currently_working'],
                 'location' => $request['location'],
                 'selectEmpType' => $request['selectEmpType'],
                 'locationType' => $request['locationType'],
                 'starting_date' => $request['startingDate'],
                 'end_date' => $request['endDate'],
                 'positionType' => $request['position'],
             ]);
             $experiance->save();

            Post::create([
                'content'=>'Work Experiance updated',
                'posting_date'=>Carbon::now(),
                'user_id'=>Auth::user()->id,
            ]);

            if(!$experiance)
            {
                throw new \Exception('work experiance not edit correctly');
            }
             return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>'successfully updated work experiance',
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @editExperianceDetails: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteExperiance($id)
    {
        try{

            $experiance = WorkExperiance::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();
            $experiance->delete();

            if(!$experiance)
            {
                throw new \Exception('work experiance delete correctly');
            }
            Post::create([
                'content'=>'WorkExperiance deleted',
                'posting_date'=>Carbon::now(),
                'user_id'=>Auth::user()->id,
            ]);
             return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>'successfully deleted work experiance',
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @deleteExperiance: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function addEducation($request)
    {
        try{

            $education = new Education();
            $education->school = $request['school'];
            $education->degree = $request['degree'];
            $education->field_of_study = $request['field_of_study'];
            $education->category = $request['degree_category'];
            $education->user_id = Auth::user()->id;
            $education->save();

            Post::create([
                'content'=>'education details added',
                'posting_date'=>Carbon::now(),
                'user_id'=>Auth::user()->id,
            ]);

            return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>'successfully add the education details',
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @addEducation: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getEducationDetails($userSlug)
    {
         try{

            $uuid = Str::match(
                        '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
                        $userSlug
                    );

             $profileDetails = User::with('profile')->whereHas('profile', function($q)use($uuid) {
                    $q->where('uuid',$uuid);
                })->get();
            $education = Education::where('user_id',$profileDetails[0]->id)->get();

            return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>$education,
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @getEducationDetails: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getEducationDetail($id)
    {
        try{
            $education = Education::where('user_id',Auth::user()->id)->where('id',$id)->get();

            return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>$education,
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @getEducationDetails: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteEducation($id)
    {
         try{

            $education = Education::where('user_id', Auth::id())
                ->where('id', $id)
                ->firstOrFail();
            $education->delete();

            //  Post::create([
            //     'content'=>'education details deleted',
            //     'posting_date'=>Carbon::now(),
            //     'user_id'=>Auth::user()->id,
            // ]);

            return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>'successfully delete the education details',
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @deleteEducation: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function editEducation($request)
    {
        try{

            $education = Education::where('user_id', Auth::id())
                ->where('id', $request['id'])
                ->firstOrFail();
            $education->fill([
                'school' => $request['school'],
                'degree' => $request['degree'],
                'category' => $request['degree_category'],
                'field_of_study' => $request['field_of_study'],
            ]);
            $education->save();

             Post::create([
                'content'=>'education details updated',
                'posting_date'=>Carbon::now(),
                'user_id'=>Auth::user()->id,
            ]);

            return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>'successfully delete the education details',
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @deleteEducation: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function addSkill($request)
    {
         try{
            $type = ($request->type) ? intval($request->type) : 0;
            $education = new Skill();
            $education->skill = $request['skill'];
            $education->type = $type;
            $education->user_id = Auth::user()->id;
            $education->save();

             Post::create([
                'content'=>'skill details added',
                'posting_date'=>Carbon::now(),
                'user_id'=>Auth::user()->id,
            ]);

            return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>'successfully Add the skill details',
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @addSkill: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getSkills()
    {
         try{
            $education = Skill::where('user_id',Auth::user()->id)->get();


            $formated=[
                'licensed'=>$education->where('type',0),
                'vocational'=>$education->where('type',1),
                'recognized' => $education->where('type',2),
            ];

            return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>$formated,
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @getSkills: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteSkill($id)
    {
         try{
            $education = Skill::where('id',$id)->where('user_id',Auth::user()->id);
            $education->delete();

            Post::create([
                'content'=>'skill details deleted',
                'posting_date'=>Carbon::now(),
                'user_id'=>Auth::user()->id,
            ]);

            return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>'successfully deleted the skill',
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @deleteSkill: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadProfileImage($request)
    {
         try{
            $updatedProfiles = Profile::where('user_id',Auth::user()->id)->update([
                'profile_image'=>$request['image']
            ]);
            if ($updatedProfiles === 0 && !Profile::where('user_id', Auth::user()->id)->exists()) {
                throw new \RuntimeException('Profile not found. Complete your basic profile before uploading a photo.');
            }
             Post::create([
                'content'=>'Profile Image changed',
                'post_image'=>$request['image'],
                'posting_date'=>Carbon::now(),
                'user_id'=>Auth::user()->id,
            ]);
            auth()->user()->notify(new NewUserNotification("Your Profile Picture Change!"));
            return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>'successfully upload the image',
            ], 200);
            

        }catch(\Exception $e){
            log::error('ProfileService @uploadProfileImage: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadCoverImage($request)
    {
        try{
            $updatedProfiles = Profile::where('user_id',Auth::user()->id)->update([
                'cover_image'=>$request['image']
            ]);
            if ($updatedProfiles === 0 && !Profile::where('user_id', Auth::user()->id)->exists()) {
                throw new \RuntimeException('Profile not found. Complete your basic profile before uploading a cover photo.');
            }
            Post::create([
                'content'=>'Cover Image changed',
                'post_image'=>$request['image'],
                'posting_date'=>Carbon::now(),
                'user_id'=>Auth::user()->id,
            ]);
            auth()->user()->notify(new NewUserNotification("Your Cover Picture Change!"));
            return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>'successfully upload the image',
            ], 200);
            

        }catch(\Exception $e){
            log::error('ProfileService @uploadCoverImage: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function profileList()
    {
        try{

            $profileDetails = User::with(['workExperiances' => function ($query) {
                    $query->where('currently_working', 1);
            }])->with('profile', function ($q) {
                        $q->whereNotNull('first_name');
                        $q->whereNotNUll('last_name');
            })->whereNot('id',Auth::user()->id)->orderBy('total_points','desc')->get();


        $Details = $profileDetails->map(function($detail){

                return [
                    'id'=>$detail->id,
                    'first_name'=>($detail->profile)? $detail->profile['first_name'] : '',
                    'last_name'=>($detail->profile) ? $detail->profile['last_name'] :'',
                    'profile_image'=>($detail->profile) ? $detail->profile['profile_image'] :null,
                    'points'=>$detail->total_points,
                    'hip_score'=>$detail->hip_score,
                    'profession'=>$detail->workExperiances->first()?->title ?? '',
                    'profile_url'=> ($detail->profile) ? $detail->profile['slug']."-".$detail->profile['uuid'] : null,
                    'rank'=>$detail->Rank,
                    'full_name'=> ($detail->profile) ? $detail->profile['full_name'] : null,
                    'currently_working'=> $detail->workExperiances->map(function($experiance){
                        return[
                            'company'=>$experiance['company'],
                            'location'=>$experiance['location']
                        ];
                    }),
                ];
        });

            if(!$profileDetails)
            {
                throw new \Exception('Profiles not found');
            }

            return response()->json([
                'code' => 200,
                'status' => true,
                'data' => $Details,
            ], 200);

        }catch(\Exception $e){
            log::error('ProfileService @getProfilesList: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function profileListPaginated($page)
    {
        try{

            $perPage = 10; // Number of profiles per page
            $skip = ($page - 1) * $perPage;

            $profileDetails = User::with(['workExperiances' => function ($query) {
                    $query->where('currently_working', 1);
            }])->with('profile', function ($q) {
                        $q->whereNotNull('first_name');
                        $q->whereNotNUll('last_name');
            })->whereNot('id',Auth::user()->id)
              ->skip($skip)
              ->take($perPage)
              ->get();  
        $Details = $profileDetails->map(function($detail){

                return [
                'id'=>$detail->id,
                'first_name'=>($detail->profile)? $detail->profile['first_name'] : '',
                'last_name'=>($detail->profile) ? $detail->profile['last_name'] :'',
                'profile_image'=>($detail->profile) ? $detail->profile['profile_image'] :null,
                'points'=>$detail->total_points,
                'hip_score'=>$detail->hip_score,
                'rank'=>$detail->Rank,
                    'profile_url'=> ($detail->profile) ? $detail->profile['slug']." - ".$detail->profile['uuid'] : null,
                    'currently_working'=> $detail->workExperiances->map(function($experiance){
                        return[
                            'company'=>$experiance['company'],
                            'location'=>$experiance['location']
                        ];
                    }),
                ];
        });

        return response()->json([
                'code' => 200,
                'status' => true,
                'data' => $Details,
            ], 200);
        }catch(\Exception $e){
                log::error('ProfileService @getProfilesListPaginated: '.$e->getMessage());
                return response()->json([
                    'code' => 500,
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
    }

    public function getOtherProfileInfomations($slug)
    {
        try{

            $uuid = substr($slug, strrpos($slug, '-') + 1);

           $profileDetails = User::with('profile')->whereHas('profile', function($q)use($uuid) {
                    $q->where('uuid',$uuid);
                })->get();

                $details = formatUserInfo($profileDetails);

             
            if(!$profileDetails)
            {
               throw new \Exception('Profiles not found');
            }

          return response()->json([
                'code' => 200,
                'status' => true,
                'data' => $details,
            ], 200);
        }catch(\Exception $e){
            log::error('ProfileService @getOtherProfileInfomations: '.$e->getMessage());
                return response()->json([
                    'code' => 500,
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 500);
        }
    }

    public function submitComplains($request)
    {
        try{
            $complainData =  $request;
            Complain::create([
                'user_id'=>$complainData['defendent'],
                'complain'=>$complainData['category'],
                'complainer'=>Auth::user()->id,
                'status'=>1,
                'from'=> $complainData['from'],
            ]);

            Jury::create([
                'user_id'=> $complainData['jury'],
                'complain_id'=> Complain::latest()->first()->id,
            ]);

          return response()->json([
                'code' => 200,
                'status' => true,
                'message'=> 'successfully added the complain'
            ], 200);
        }catch(\Exception $e){
            log::error('ProfileService @submitComplains: '.$e->getMessage());
                return response()->json([
                    'code' => 500,
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 500);
        }
    }

    public function getComplains($status)
    {
        try{

            $complains = Complain::with('user')->where('status',$status)->where('complainer',auth()->user()->id)->where('from',1)->orderBy('created_at','desc')->get()->map(function($complain){
                $jury = Jury::where('complain_id',$complain->id)->first();
                return[
                    'user'=>$complain->user->profile->full_name,
                    'complain_from'=>$complain->complainerUser->profile->full_name,
                    'jury'=>($this->juryList($complain->id))? $this->juryList($complain->id): 'Not Assigned',
                    'complain'=>$complain->complain,
                    'status'=>$complain->status,
                    'date'=>$complain->created_at->toDateString()
                ];
            });

            $ExternalComplains = Complain::with('user')->where('status',$status)->where('complainer',auth()->user()->id)->where('from',2)->orderBy('created_at','desc')->get()->map(function($complain){
                return[
                    'user'=>$complain->user->profile->full_name,
                    'complain_from'=>$complain->complainerUser->profile->full_name,
                    'complain'=>$complain->complain,
                    'status'=>$complain->status,
                    'date'=>$complain->created_at->toDateString()
                ];
            });

          return response()->json([
                'code' => 200,
                'status' => true,
                'data'=> ['complains'=>$complains,'external_complains'=>$ExternalComplains],
            ], 200);
        }catch(\Exception $e){
            log::error('ProfileService @getComplains: '.$e->getMessage());
                return response()->json([
                    'code' => 500,
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 500);
        }
    }

    public function juryList($complain_id)
    {
       
            $juryList = Jury::with('user')->where('complain_id',$complain_id)->get()->map(function($jury){
                return[
                    'id'=>$jury->id,
                    'name'=>$jury->user->profile->full_name,
                    'profile_image'=>$jury->user->profile->profile_image,
                    'profile_url'=>$jury->user->profile->slug."-".$jury->user->profile->uuid,
                ];
            });

          return $juryList;
    }

    public function StoreScore($education){

        switch ($education->category) {
            case 'Masters':
                $user = auth()->user()->id;
                    ScoreEvent::dispatch(1,$user,Education::class,$education->id);
                break;

            case 'Bachelors':
                $user = auth()->user()->id;
                    ScoreEvent::dispatch(2,$user,Education::class,$education->id);
                break;

            case 'Diploma':
                $user = auth()->user()->id;
                    ScoreEvent::dispatch(3,$user,Education::class,$education->id);
                break;

            case 'Certificates':
                $user = auth()->user()->id;
                    ScoreEvent::dispatch(4,$user,Education::class,$education->id);
                break;

            case 'PhD':
                $user = auth()->user()->id;
                $request=[
                    "category"=> "PhD / Doctorate",
                    "defendent"=>auth()->user()->id,
                    "from"=>auth()->user()->id,
                ];
                    $this->submitComplains($request);
                break;
            
            default:
                # code...
                break;
        }
    }

    

}