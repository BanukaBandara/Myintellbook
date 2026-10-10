<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tribunal\TribunalCaseController;

Route::middleware('throttle:10,1')->post('/register', [\App\Http\Controllers\UserController::class, 'userRegister']);
Route::middleware('throttle:10,1')->post('/verify-email', [\App\Http\Controllers\UserController::class, 'verifyEmail']);
// Named limiters are defined in AppServiceProvider::configureRateLimiting().
Route::middleware('throttle:login')->post('/login', [\App\Http\Controllers\UserController::class, 'userLogin']);
Route::middleware('throttle:login')->post('/admin/login', [\App\Http\Controllers\Admin\AdminAuthController::class, 'login']);
Route::middleware('throttle:password-reset')->post('/password/reset', [\App\Http\Controllers\UserController::class, 'passwordResetLink']);
Route::middleware('throttle:password-reset')->post('/password/reset/{token}', [\App\Http\Controllers\UserController::class, 'passwordReset']);
Route::middleware('throttle:10,1')->post('auth/google', [\App\Http\Controllers\GoogleController::class, 'callback']);
Route::middleware('throttle:10,1')->get('auth/google/callback', [\App\Http\Controllers\GoogleController::class, 'callback']);

// Public Tribunal Report Authenticity Verification (Rate limited)
Route::middleware('throttle:60,1')->get('/tribunal/reports/verify/{verificationCode}', [\App\Http\Controllers\Tribunal\TribunalCaseReportController::class, 'verifyCode']);
Route::middleware('throttle:30,1')->post('/tribunal/reports/verify-file', [\App\Http\Controllers\Tribunal\TribunalCaseReportController::class, 'verifyFile']);


 

 
// Every authenticated endpoint gets the default 'api' ceiling; individual routes add tighter
// limits ('search', 'writes') and 'verified.email' for actions that need a confirmed address.
Route::middleware(['auth.token', 'throttle:api'])->group(function () {
    Route::get('/user', [\App\Http\Controllers\UserController::class, 'userCheck']);
    Route::post('/email/verification-notification', [\App\Http\Controllers\UserController::class, 'resendVerificationEmail'])
        ->middleware('throttle:verification-email');
    Route::get('/user/profile', [\App\Http\Controllers\ProfileController::class, 'userProfile']);
    Route::get('/users/{id}', [\App\Http\Controllers\ProfileController::class, 'show'])
        ->whereNumber('id')
        ->middleware('throttle:60,1');
    Route::get('/categories', [\App\Http\Controllers\CategoryController::class, 'getCategories']); 
    Route::get('/professions/{category_id}',[\App\Http\Controllers\CategoryController::class, 'getProfessions']);
    Route::post('/insert-profile',[\App\Http\Controllers\ProfileController::class, 'insert'])->middleware('restrict.feature:profile_editing');
    Route::get('/user-data', [\App\Http\Controllers\ProfileController::class, 'userData']);
    Route::post('/edit-general-info',[\App\Http\Controllers\ProfileController::class,'editGeneralInfo'])->middleware('restrict.feature:profile_editing');
    Route::get('/log-out',[\App\Http\Controllers\UserController::class, 'logOut']);
    Route::get('/get-general-info',[\App\Http\Controllers\ProfileController::class,'gtGeneralInfo']);
    Route::post('/add-work-experiance',[\App\Http\Controllers\ExperienceController::class,'store'])->middleware('restrict.feature:profile_editing');
    Route::get('/get-work-experiances/{userSlug}',[\App\Http\Controllers\ExperienceController::class, 'index']);
    Route::get('/get-details-experiance/{id}',[\App\Http\Controllers\ExperienceController::class,'show']);
    Route::post('edit-details-experiance',[\App\Http\Controllers\ExperienceController::class,'update'])->middleware('restrict.feature:profile_editing');
    Route::get('/delete-experiance/{id}',[\App\Http\Controllers\ExperienceController::class,'destroy'])->middleware('restrict.feature:profile_editing');
    Route::post('/add-education-details',[\App\Http\Controllers\EducationController::class, 'store'])->middleware('restrict.feature:profile_editing');
    Route::get('/get-education-details/{userSlug}',[\App\Http\Controllers\EducationController::class, 'index']);
    Route::get('/get-education-detail/{id}',[\App\Http\Controllers\EducationController::class,'show']);
    Route::get('/delete-education/{id}',[\App\Http\Controllers\EducationController::class,'destroy'])->middleware('restrict.feature:profile_editing');
    Route::post('/edit-education-detail',[\App\Http\Controllers\EducationController::class, 'update'])->middleware('restrict.feature:profile_editing');
    Route::post('/add-skill',[\App\Http\Controllers\ProfileController::class,'addSkill'])->middleware(['throttle:writes', 'restrict.feature:profile_editing']);
    Route::get('/get-skills',[\App\Http\Controllers\ProfileController::class,'getSkills']);
    Route::get('/delete-skill/{id}',[\App\Http\Controllers\ProfileController::class,'deleteSkill'])->middleware('restrict.feature:profile_editing');
    Route::post('/upload-profile-image',[\App\Http\Controllers\ProfileController::class,'uploadProfileImage'])->middleware('restrict.feature:profile_editing');
    Route::post('/upload-cover-image',[\App\Http\Controllers\ProfileController::class,'uploadCoverImage'])->middleware('restrict.feature:profile_editing');
    Route::get('/profile-completed-status',[\App\Http\Controllers\ProfileController::class,'checkProfileCompleted']);
    Route::get('/get-user-summary',[\App\Http\Controllers\ProfileController::class,'basicInfo']);
    Route::get('/profile-list',[\App\Http\Controllers\ProfileController::class,'profileList']);
    Route::get('/request-today-question', [\App\Http\Controllers\QuestionController::class, 'getTodaySpecialQuestions']);
    Route::get('/daily-question/today', [\App\Http\Controllers\DailyQuestionController::class, 'getTodayQuestion'])->middleware('throttle:60,1');
    Route::post('/submit-daily-answer', [\App\Http\Controllers\DailyQuestionController::class, 'submitDailyAnswer'])->middleware(['throttle:10,1', 'restrict.feature:daily_question_access']);
    Route::post('/daily-questions/answer', [\App\Http\Controllers\DailyQuestionController::class, 'submitDailyAnswer'])->middleware(['throttle:10,1', 'restrict.feature:daily_question_access']);
    Route::get('/daily-questions/history', [\App\Http\Controllers\DailyQuestionController::class, 'history'])->middleware('throttle:60,1');
    Route::get('/learn/categories', [\App\Http\Controllers\LearnController::class, 'categories'])->middleware('throttle:60,1');
    Route::get('/learn/questions/{category}', [\App\Http\Controllers\LearnController::class, 'questions'])
        ->whereNumber('category')
        ->middleware('throttle:60,1');
    Route::get('/learn/active', [\App\Http\Controllers\LearnController::class, 'active'])->middleware('throttle:60,1');
    Route::post('/learn/enroll', [\App\Http\Controllers\LearnController::class, 'enroll'])->middleware('throttle:10,1');
    Route::post('/learn/next', [\App\Http\Controllers\LearnController::class, 'nextBatch'])->middleware('throttle:10,1');
    Route::get('/exam/categories', [\App\Http\Controllers\ExamSessionController::class, 'categories'])->middleware('throttle:60,1');
    Route::post('/exam/start', [\App\Http\Controllers\ExamSessionController::class, 'start'])->middleware(['throttle:10,1', 'restrict.feature:exam_access']);
    Route::post('/exam/submit', [\App\Http\Controllers\ExamSessionController::class, 'submit'])->middleware(['throttle:10,1', 'restrict.feature:exam_access']);
    Route::post('/generate-questions', [\App\Http\Controllers\QuestionController::class, 'generateQuestions']);
    Route::post('/set-user-answer', [\App\Http\Controllers\DailyQuestionController::class, 'submitDailyAnswer'])->middleware(['throttle:10,1', 'restrict.feature:daily_question_access']);
    Route::post('/set-comment', [\App\Http\Controllers\CommentController::class, 'setComment'])->middleware(['throttle:writes', 'restrict.feature:community_posting']);
    Route::get('/get-scores',[\App\Http\Controllers\ScoreController::class, 'getScores']);
    Route::get('/scores/lci', [\App\Http\Controllers\UserScoreController::class, 'lci'])->middleware('throttle:60,1');

    // Fixed Arm for Testament Management (FATM)
    Route::prefix('testament')->middleware('throttle:30,1')->group(function () {
        Route::get('/notes', [\App\Http\Controllers\TestamentController::class, 'publicFeed']);
        Route::get('/public-feed', [\App\Http\Controllers\TestamentController::class, 'publicFeed']);
        Route::post('/notes', [\App\Http\Controllers\TestamentController::class, 'createResourceNote'])->middleware(['throttle:writes', 'restrict.feature:community_posting']);
        Route::get('/my-notes', [\App\Http\Controllers\TestamentController::class, 'myNotes']);
        Route::put('/notes/{id}', [\App\Http\Controllers\TestamentController::class, 'updateResourceNote'])->whereNumber('id')->middleware('restrict.feature:community_posting');
        Route::delete('/notes/{id}', [\App\Http\Controllers\TestamentController::class, 'deleteResourceNote'])->whereNumber('id')->middleware('restrict.feature:community_posting');
        Route::get('/', [\App\Http\Controllers\TestamentController::class, 'show']);
        Route::put('/', [\App\Http\Controllers\TestamentController::class, 'save']);
        Route::post('/submit', [\App\Http\Controllers\TestamentController::class, 'submit'])->middleware('verified.email');
        Route::post('/recall', [\App\Http\Controllers\TestamentController::class, 'recall']);
        Route::post('/withdraw', [\App\Http\Controllers\TestamentController::class, 'withdraw']);
        Route::get('/witness-requests', [\App\Http\Controllers\TestamentController::class, 'witnessRequests']);
        Route::post('/witness-requests/{testament}/{decision}', [\App\Http\Controllers\TestamentController::class, 'witnessRespond'])
            ->whereNumber('testament')
            ->whereIn('decision', ['confirm', 'decline']);
    });
    Route::get('/get-comments', [\App\Http\Controllers\CommentController::class, 'getComments']);
    Route::get('/notifications',[\App\Http\Controllers\NotificationsController::class,'getNotifications']);
    Route::get('/allNotifications',[\App\Http\Controllers\NotificationsController::class,'getAll']);
    Route::get('/topScores',[\App\Http\Controllers\ScoreController::class, 'topScores']);
    Route::post('/search',[\App\Http\Controllers\ProfileController::class, 'search'])->middleware('throttle:search');
    Route::get('/profile-list-paginated/{page}',[\App\Http\Controllers\ProfileController::class,'profileListPaginated'])->whereNumber('page')->middleware('throttle:search');
    Route::get('/get-user-infomations/{slug}',[\App\Http\Controllers\ProfileController::class,'getOtherProfileInfomations'])->middleware('throttle:search');
    Route::post('/create_exam',[\App\Http\Controllers\ExamController::class,'create'])->middleware(['throttle:writes', 'restrict.feature:exam_access']);
    Route::get('/get_exams',[\App\Http\Controllers\ExamController::class,'getExams']);
    Route::get('/categories_with_professions',[\App\Http\Controllers\CategoryController::class,'getformated']);
    Route::post('/save_exam',[\App\Http\Controllers\ExamController::class,'saveExam'])->middleware('restrict.feature:exam_access');
    Route::get('/my_exams',[\App\Http\Controllers\ExamController::class,'getMyExams']);
    Route::get('/my_caetgories',[\App\Http\Controllers\CategoryController::class,'getMyCategories']);
    Route::get('/get_exam_data',[\App\Http\Controllers\ExamController::class,'getExamData']);
    Route::post('/submit_answers',[\App\Http\Controllers\ExamController::class,'submitAnswers'])->middleware('restrict.feature:exam_access');
    Route::post('/check_answer',[\App\Http\Controllers\ExamController::class,'checkAnswer']);
    Route::get('/get-exam-summary/{examId}',[\App\Http\Controllers\ExamController::class,'getSummary']);
    Route::get('/add-category-user/{categoryId}',[\App\Http\Controllers\CategoryController::class,'addUserCategory']);
    Route::get('/delete-account',[\App\Http\Controllers\UserController::class,'DeleteUser']);
    Route::post('/set_settings',[\App\Http\Controllers\UserSettingsController::class,'setSettings'])->middleware('throttle:writes');
    Route::get('/get_settings',[\App\Http\Controllers\UserSettingsController::class,'getSettings']);
    Route::post('/submit-complains',[\App\Http\Controllers\ProfileController::class,'submitComplains'])->middleware(['throttle:writes', 'verified.email']);
    Route::get('/get-complains/{status}',[\App\Http\Controllers\ProfileController::class,'getComplains'])->whereNumber('status');

    Route::prefix('tribunal')->group(function () {
        Route::get('/me', \App\Http\Controllers\Tribunal\TribunalMeController::class);
        // Respondent Candidate Search (Case Filing Flow)
        Route::get('/respondents/search', [\App\Http\Controllers\Tribunal\TribunalRespondentSearchController::class, 'search'])->middleware('throttle:search');
        Route::post('/cases', [TribunalCaseController::class, 'store'])->middleware(['throttle:writes', 'verified.email', 'restrict.feature:tribunal_participation']);
        Route::get('/cases', [TribunalCaseController::class, 'index']);
        Route::get('/cases/{tribunalCase}', [TribunalCaseController::class, 'show']);
        Route::post('/cases/{tribunalCase}/acknowledge', [TribunalCaseController::class, 'acknowledge'])->middleware('restrict.feature:tribunal_participation');
        Route::post('/cases/{tribunalCase}/response', [TribunalCaseController::class, 'submitResponse'])->middleware('restrict.feature:tribunal_participation');

        // Evidence Management
        Route::get('/cases/{tribunalCase}/evidence', [\App\Http\Controllers\Tribunal\TribunalEvidenceController::class, 'index']);
        Route::post('/cases/{tribunalCase}/evidence', [\App\Http\Controllers\Tribunal\TribunalEvidenceController::class, 'store'])->middleware(['throttle:writes', 'verified.email', 'restrict.feature:tribunal_participation']);
        Route::get('/cases/{tribunalCase}/evidence/{evidence}', [\App\Http\Controllers\Tribunal\TribunalEvidenceController::class, 'show']);
        Route::get('/cases/{tribunalCase}/evidence/{evidence}/download', [\App\Http\Controllers\Tribunal\TribunalEvidenceController::class, 'download']);
        Route::post('/cases/{tribunalCase}/evidence/{evidence}/challenge', [\App\Http\Controllers\Tribunal\TribunalEvidenceController::class, 'challenge'])->middleware('restrict.feature:tribunal_participation');

        // Jury / Adjudicator System
        Route::get('/jury/cases', [\App\Http\Controllers\Tribunal\TribunalJuryController::class, 'jurorCases']);
        Route::post('/cases/{tribunalCase}/jury/select', [\App\Http\Controllers\Tribunal\TribunalJuryController::class, 'select']);
        Route::post('/cases/{tribunalCase}/jury/conflict', [\App\Http\Controllers\Tribunal\TribunalJuryController::class, 'declareConflict']);
        Route::post('/cases/{tribunalCase}/jury/accept', [\App\Http\Controllers\Tribunal\TribunalJuryController::class, 'accept']);
        Route::post('/cases/{tribunalCase}/jury/recuse', [\App\Http\Controllers\Tribunal\TribunalJuryController::class, 'recuse']);

        // Legal Representation & Verified Representatives
        Route::get('/representatives', [\App\Http\Controllers\Tribunal\TribunalRepresentationController::class, 'representatives'])->middleware('throttle:search');
        Route::post('/cases/{tribunalCase}/representation-requests', [\App\Http\Controllers\Tribunal\TribunalRepresentationController::class, 'storeRequest'])->middleware(['throttle:writes', 'verified.email', 'restrict.feature:tribunal_participation']);
        Route::get('/representation-requests', [\App\Http\Controllers\Tribunal\TribunalRepresentationController::class, 'lawyerRequests']);
        Route::post('/representation-requests/{representationRequest}/accept', [\App\Http\Controllers\Tribunal\TribunalRepresentationController::class, 'accept'])->middleware('restrict.feature:tribunal_participation');
        Route::post('/representation-requests/{representationRequest}/decline', [\App\Http\Controllers\Tribunal\TribunalRepresentationController::class, 'decline'])->middleware('restrict.feature:tribunal_participation');
        Route::post('/cases/{tribunalCase}/representation/end', [\App\Http\Controllers\Tribunal\TribunalRepresentationController::class, 'end'])->middleware('restrict.feature:tribunal_participation');
        Route::get('/represented-cases', [\App\Http\Controllers\Tribunal\TribunalRepresentationController::class, 'representedCases']);
        Route::get('/cases/{tribunalCase}/representation', [\App\Http\Controllers\Tribunal\TribunalRepresentationController::class, 'caseRepresentation']);

        // Private Client-Representative Conversations
        Route::get('/cases/{tribunalCase}/representative-conversation', [\App\Http\Controllers\Tribunal\TribunalConversationController::class, 'showForCase']);
        Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\Tribunal\TribunalConversationController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [\App\Http\Controllers\Tribunal\TribunalConversationController::class, 'sendMessage'])->middleware('restrict.feature:tribunal_participation');

        // Shared Tribunal Case Room & Procedural Communication (Batch 5)
        Route::get('/cases/{tribunalCase}/case-room', [\App\Http\Controllers\Tribunal\TribunalCaseRoomController::class, 'show']);
        Route::get('/cases/{tribunalCase}/case-room/messages', [\App\Http\Controllers\Tribunal\TribunalCaseRoomController::class, 'messages']);
        Route::post('/cases/{tribunalCase}/case-room/messages', [\App\Http\Controllers\Tribunal\TribunalCaseRoomController::class, 'sendMessage'])->middleware('restrict.feature:tribunal_participation');
        Route::post('/cases/{tribunalCase}/case-room/procedural-notices', [\App\Http\Controllers\Tribunal\TribunalCaseRoomController::class, 'postProceduralNotice']);
        Route::post('/cases/{tribunalCase}/case-room/questions', [\App\Http\Controllers\Tribunal\TribunalCaseRoomController::class, 'askQuestion']);
        Route::post('/cases/{tribunalCase}/case-room/questions/{question}/responses', [\App\Http\Controllers\Tribunal\TribunalCaseRoomController::class, 'respondToQuestion'])->middleware('restrict.feature:tribunal_participation');

        // Structured Mediation & Settlement (Batch 5)
        Route::get('/cases/{tribunalCase}/mediation', [\App\Http\Controllers\Tribunal\TribunalMediationController::class, 'showForCase']);
        Route::post('/cases/{tribunalCase}/mediation/request', [\App\Http\Controllers\Tribunal\TribunalMediationController::class, 'requestMediation'])->middleware('restrict.feature:tribunal_participation');
        Route::post('/cases/{tribunalCase}/mediation/offer', [\App\Http\Controllers\Tribunal\TribunalMediationController::class, 'offerMediation']);
        Route::post('/mediations/{mediation}/respond', [\App\Http\Controllers\Tribunal\TribunalMediationController::class, 'respond'])->middleware('restrict.feature:tribunal_participation');
        Route::post('/mediations/{mediation}/end', [\App\Http\Controllers\Tribunal\TribunalMediationController::class, 'endMediation'])->middleware('restrict.feature:tribunal_participation');
        Route::post('/mediations/{mediation}/proposals', [\App\Http\Controllers\Tribunal\TribunalMediationController::class, 'createProposal'])->middleware('restrict.feature:tribunal_participation');
        Route::post('/mediations/{mediation}/proposals/{proposal}/counter', [\App\Http\Controllers\Tribunal\TribunalMediationController::class, 'counterProposal'])->middleware('restrict.feature:tribunal_participation');
        Route::post('/settlement-proposals/{proposal}/accept', [\App\Http\Controllers\Tribunal\TribunalMediationController::class, 'acceptProposal'])->middleware('restrict.feature:tribunal_participation');
        Route::post('/settlement-proposals/{proposal}/reject', [\App\Http\Controllers\Tribunal\TribunalMediationController::class, 'rejectProposal'])->middleware('restrict.feature:tribunal_participation');

        // Formal Hearing (Step 5)
        Route::get('/cases/{tribunalCase}/hearings', [\App\Http\Controllers\Tribunal\TribunalHearingController::class, 'index']);
        Route::get('/hearings/{hearing}', [\App\Http\Controllers\Tribunal\TribunalHearingController::class, 'show']);
        Route::post('/hearings/{hearing}/entries', [\App\Http\Controllers\Tribunal\TribunalHearingController::class, 'addEntry'])->middleware('restrict.feature:tribunal_participation');
        Route::post('/hearings/{hearing}/questions/{question}/responses', [\App\Http\Controllers\Tribunal\TribunalHearingController::class, 'respondToQuestion'])->middleware('restrict.feature:tribunal_participation');

        // Final Decision & Outcomes (Step 6)
        Route::get('/cases/{tribunalCase}/decision', [\App\Http\Controllers\Tribunal\TribunalDecisionController::class, 'show']);

        // Official Tribunal Reports & Downloads
        Route::get('/cases/{tribunalCase}/reports', [\App\Http\Controllers\Tribunal\TribunalCaseReportController::class, 'index']);
        Route::middleware(['throttle:10,1', 'restrict.feature:tribunal_participation'])->post('/cases/{tribunalCase}/reports/final', [\App\Http\Controllers\Tribunal\TribunalCaseReportController::class, 'generateFinalReport']);
        Route::get('/reports/{report}', [\App\Http\Controllers\Tribunal\TribunalCaseReportController::class, 'show']);
        Route::get('/reports/{report}/download', [\App\Http\Controllers\Tribunal\TribunalCaseReportController::class, 'download']);
    });

    // Professional Verifications (User)
    Route::post('/professional-verifications', [\App\Http\Controllers\Professional\ProfessionalVerificationController::class, 'apply'])->middleware(['throttle:writes', 'verified.email']);
    Route::get('/professional-verifications/me', [\App\Http\Controllers\Professional\ProfessionalVerificationController::class, 'myVerification']);

    // Professional Verifications (Admin / Reviewer)
    Route::middleware('admin')->prefix('admin/professional-verifications')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminProfessionalVerificationController::class, 'index']);
        Route::get('/{verification}', [\App\Http\Controllers\Admin\AdminProfessionalVerificationController::class, 'show']);
        Route::post('/{verification}/approve', [\App\Http\Controllers\Admin\AdminProfessionalVerificationController::class, 'approve']);
        Route::post('/{verification}/reject', [\App\Http\Controllers\Admin\AdminProfessionalVerificationController::class, 'reject']);
        Route::post('/{verification}/suspend', [\App\Http\Controllers\Admin\AdminProfessionalVerificationController::class, 'suspend']);
        Route::get('/{verification}/documents/{documentType}', [\App\Http\Controllers\Admin\AdminProfessionalVerificationController::class, 'downloadDocument']);
    });

    // Jury Panel Management (Super Admin)
    Route::middleware('admin')->prefix('admin/tribunal/jury-panels')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminTribunalJuryPanelController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\AdminTribunalJuryPanelController::class, 'store']);
        Route::get('/{juryPanel}', [\App\Http\Controllers\Admin\AdminTribunalJuryPanelController::class, 'show']);
        Route::patch('/{juryPanel}', [\App\Http\Controllers\Admin\AdminTribunalJuryPanelController::class, 'update']);
        Route::post('/{juryPanel}/activate', [\App\Http\Controllers\Admin\AdminTribunalJuryPanelController::class, 'activate']);
        Route::post('/{juryPanel}/deactivate', [\App\Http\Controllers\Admin\AdminTribunalJuryPanelController::class, 'deactivate']);
    });

    // Dedicated Super Admin Authentication & Dashboard
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Admin\AdminAuthController::class, 'logout']);
        Route::get('/me', [\App\Http\Controllers\Admin\AdminAuthController::class, 'me']);
        Route::get('/dashboard/stats', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'stats']);
    });

    // Dedicated Jury Panel Portal (Step 2, Step 3, Step 4, Step 5, Step 6)
    Route::middleware('jury.panel')->prefix('jury')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Jury\JuryPortalController::class, 'me']);
        Route::get('/cases', [\App\Http\Controllers\Jury\JuryPortalController::class, 'cases']);
        Route::get('/cases/{id}', [\App\Http\Controllers\Jury\JuryPortalController::class, 'showCase']);

        // Case Room endpoints for assigned Jury Panel (Step 4)
        Route::get('/cases/{id}/case-room/messages', [\App\Http\Controllers\Jury\JuryPortalController::class, 'caseRoomMessages']);
        Route::post('/cases/{id}/case-room/messages', [\App\Http\Controllers\Jury\JuryPortalController::class, 'sendCaseRoomMessage']);
        Route::post('/cases/{id}/case-room/procedural-notices', [\App\Http\Controllers\Jury\JuryPortalController::class, 'postProceduralNotice']);
        Route::post('/cases/{id}/case-room/questions', [\App\Http\Controllers\Jury\JuryPortalController::class, 'askQuestion']);

        // Mediation Oversight endpoints for assigned Jury Panel (Step 4)
        Route::get('/cases/{id}/mediation', [\App\Http\Controllers\Jury\JuryPortalController::class, 'mediationShow']);
        Route::post('/cases/{id}/mediation/offer', [\App\Http\Controllers\Jury\JuryPortalController::class, 'mediationOffer']);
        Route::post('/cases/{id}/mediation/end', [\App\Http\Controllers\Jury\JuryPortalController::class, 'mediationEnd']);

        // Hearing Management for assigned Jury Panel (Step 5)
        Route::get('/cases/{id}/hearings', [\App\Http\Controllers\Jury\JuryHearingController::class, 'index']);
        Route::post('/cases/{id}/hearings', [\App\Http\Controllers\Jury\JuryHearingController::class, 'schedule']);
        Route::get('/hearings/{hearing}', [\App\Http\Controllers\Jury\JuryHearingController::class, 'show']);
        Route::post('/hearings/{hearing}/start', [\App\Http\Controllers\Jury\JuryHearingController::class, 'start']);
        Route::post('/hearings/{hearing}/recess', [\App\Http\Controllers\Jury\JuryHearingController::class, 'recess']);
        Route::post('/hearings/{hearing}/resume', [\App\Http\Controllers\Jury\JuryHearingController::class, 'resume']);
        Route::post('/hearings/{hearing}/close', [\App\Http\Controllers\Jury\JuryHearingController::class, 'close']);
        Route::post('/hearings/{hearing}/entries', [\App\Http\Controllers\Jury\JuryHearingController::class, 'addEntry']);
        Route::post('/hearings/{hearing}/questions', [\App\Http\Controllers\Jury\JuryHearingController::class, 'askQuestion']);

        // Deliberation, Findings & Final Decision for assigned Jury Panel (Step 6)
        Route::get('/cases/{id}/deliberation', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'show']);
        Route::post('/cases/{id}/deliberation/notes', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'addNote']);
        Route::patch('/cases/{id}/deliberation/notes/{note}', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'updateNote']);
        Route::delete('/cases/{id}/deliberation/notes/{note}', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'deleteNote']);

        Route::post('/cases/{id}/findings', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'addFinding']);
        Route::patch('/cases/{id}/findings/{finding}', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'updateFinding']);
        Route::delete('/cases/{id}/findings/{finding}', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'deleteFinding']);

        Route::get('/cases/{id}/decision', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'getDecision']);
        Route::post('/cases/{id}/decision', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'updateDecision']);
        Route::patch('/cases/{id}/decision', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'updateDecision']);
        Route::post('/cases/{id}/decision/orders', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'addOrder']);
        Route::patch('/cases/{id}/decision/orders/{order}', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'updateOrder']);
        Route::delete('/cases/{id}/decision/orders/{order}', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'deleteOrder']);
        Route::post('/cases/{id}/decision/publish', [\App\Http\Controllers\Jury\JuryDeliberationController::class, 'publish']);
    });

    // Internal Tribunal / Misconduct Reporting (User)
    Route::prefix('internal-reports')->group(function () {
        Route::get('/users/search', [\App\Http\Controllers\InternalTribunal\InternalReportController::class, 'searchUsers'])->middleware('throttle:search');
        Route::post('/', [\App\Http\Controllers\InternalTribunal\InternalReportController::class, 'store'])->middleware(['throttle:writes', 'verified.email']);
        Route::get('/', [\App\Http\Controllers\InternalTribunal\InternalReportController::class, 'index']);
        Route::get('/{report}', [\App\Http\Controllers\InternalTribunal\InternalReportController::class, 'show']);
        Route::get('/evidence/{evidence}/download', [\App\Http\Controllers\InternalTribunal\InternalReportController::class, 'downloadEvidence']);
    });

    // Internal Tribunal Reports Review (Super Admin)
    Route::middleware('admin')->prefix('admin/internal-reports')->group(function () {
        Route::get('/', [\App\Http\Controllers\InternalTribunal\AdminInternalReportController::class, 'index']);
        Route::get('/{report}', [\App\Http\Controllers\InternalTribunal\AdminInternalReportController::class, 'show']);
        Route::patch('/{report}/status', [\App\Http\Controllers\InternalTribunal\AdminInternalReportController::class, 'updateStatus']);
        Route::post('/{report}/penalties', [\App\Http\Controllers\InternalTribunal\AdminInternalReportController::class, 'applyPenalty']);
        Route::post('/penalties/{penalty}/reverse', [\App\Http\Controllers\InternalTribunal\AdminInternalReportController::class, 'reversePenalty']);
        Route::get('/evidence/{evidence}/download', [\App\Http\Controllers\InternalTribunal\AdminInternalReportController::class, 'downloadEvidence']);
    });
});
