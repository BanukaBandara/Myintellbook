<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\confirmMail;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Profile;
use App\Models\WorkExperiance;
use App\Models\Education;
use App\Models\Skill;
use App\Models\Post;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'google_id',
        'Rank',
        'total_points',
        'hip_score',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'hip_score' => 'float',
        ];
    }

    /**
     * Generates a verification token for the user and sends a verification email.
     *
     * The generated token is a random 40 character string.
     *
     * @return void
     */
    public function generateVerificationToken()
    {
        $this->email_verification_token = Str::random(40);
        $this->save();

        // Send email
        $this->sendVerificationEmail();
    }

    /**
     * Sends a verification email to the user.
     *
     * The email is sent using the `VerifyEmail` mailable and contains a link to the verification page.
     * The verification link is constructed by appending the user's email and verification token to
     * the `app.verification_link` config value.
     *
     * @return void
     */
    public function sendVerificationEmail()
    {
        $verificationUrl = config('app.verification_link')."?email=".$this->email."&token=". $this->email_verification_token;
        Mail::to($this->email)->send(new confirmMail($verificationUrl));
    }

    public function apiTokens() {
        return $this->hasMany(ApiToken::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * True once the onboarding Personal Details form has been saved.
     */
    public function isProfileCompleted(): bool
    {
        $profile = $this->profile;

        return $profile !== null
            && filled($profile->first_name)
            && filled($profile->last_name);
    }

    public function workExperiances()
    {
        return $this->hasMany(WorkExperiance::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class)->where('is_approved', true)->orderBy('posting_date', 'desc');
    }

    public function question()
    {
        return $this->hasOne(Question::class);
    }

    public function questionsSeen()
    {
        return $this->belongsToMany(Question::class, 'question_user')->withTimestamps();
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    public function educations()
    {
        return $this->hasMany(Education::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    public function tribunalReports(): HasMany
    {
        return $this->hasMany(TribunalReport::class);
    }

    public function userAnswers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }

    public function skills()
    {
        return $this->hasMany(Skill::class);
    }

    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'exam_user', 'user_id', 'exam_id')
                    ->withPivot('score', 'attempted_at', 'passed','answers')
                    ->withTimestamps();
    }

     public function professions()
    {
        return $this->belongsToMany(\App\Models\Profession::class,'profession_user');
    }

      public function settings()
    {
        return $this->hasMany(\App\Models\UserSettings::class);
    }

     public function getSetting($key, $default = null)
    {
        $setting = $this->settings()->where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public function setSetting($key, $value)
    {
        return $this->settings()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public function getSettings()
    {
        return $this->settings->map(function($setting){
               return [
                    'user_id'=>$setting->user_id,
                    'Key'=>$setting->key,
                    'value'=>$setting->value ?? 'Public', 
                ];
            });
    }

    public function jurorProfile(): HasOne
    {
        return $this->hasOne(TribunalJurorProfile::class, 'user_id');
    }

    public function adjudicatorProfile(): HasOne
    {
        return $this->hasOne(TribunalAdjudicatorProfile::class, 'user_id');
    }

    public function juryAssignments(): HasMany
    {
        return $this->hasMany(TribunalJuryAssignment::class, 'juror_id');
    }

    public function professionalVerifications(): HasMany
    {
        return $this->hasMany(ProfessionalVerification::class, 'user_id');
    }

    public function latestProfessionalVerification(): HasOne
    {
        return $this->hasOne(ProfessionalVerification::class, 'user_id')->latestOfMany();
    }

    public function verifiedProfessionalVerification(): HasOne
    {
        return $this->hasOne(ProfessionalVerification::class, 'user_id')
            ->where('verification_status', \App\Enums\ProfessionalVerificationStatus::Verified)
            ->latestOfMany();
    }

    public function isAdmin(): bool
    {
        return (bool) ($this->is_admin ?? false);
    }

    public function canActAsLegalRepresentative(): bool
    {
        if ($this->isJuryPanelAccount()) {
            return false;
        }

        $verification = $this->latestProfessionalVerification;

        return $verification !== null
            && $verification->isValid()
            && $verification->profession_type === \App\Enums\ProfessionalType::AttorneyAtLaw;
    }

    public function juryPanel(): HasOne
    {
        return $this->hasOne(TribunalJuryPanel::class, 'login_user_id');
    }

    public function isJuryPanelAccount(): bool
    {
        if ($this->relationLoaded('juryPanel')) {
            return $this->juryPanel !== null;
        }

        return $this->juryPanel()->exists();
    }

    public function createdJuryPanels(): HasMany
    {
        return $this->hasMany(TribunalJuryPanel::class, 'created_by');
    }

    public function representationRequestsReceived(): HasMany
    {
        return $this->hasMany(TribunalRepresentationRequest::class, 'representative_user_id');
    }

    public function representationRequestsSent(): HasMany
    {
        return $this->hasMany(TribunalRepresentationRequest::class, 'client_user_id');
    }

    public function representativeAssignments(): HasMany
    {
        return $this->hasMany(TribunalRepresentativeAssignment::class, 'representative_user_id');
    }

    public function activeRepresentativeAssignments(): HasMany
    {
        return $this->hasMany(TribunalRepresentativeAssignment::class, 'representative_user_id')
            ->where('status', \App\Enums\TribunalRepresentativeAssignmentStatus::Active);
    }
}
