<?php

namespace App\Models;
use App\Models\User;
use App\Models\Post;
use Laravel\Scout\Searchable;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use Searchable;

    protected $fillable = [
        'first_name', 
        'last_name', 
        'gender', 
        'profession_id',
        'user_id',
        'brith_date',
        'birth_date',
        'profile_image',
        'cover_image',
        'slug',
        'uuid'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

     public function posts()
    {
        return $this->hasMany(Post::class);
    }

     /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = [
            'first_name'=>$this->first_name,
            'last_name'=>$this->last_name
        ];
 
        // Customize the data array...
 
        return $array;
    }

    protected static function booted()
    {
        static::creating(function ($profile) {
            $profile->slug = Str::slug($profile->first_name . ' ' . $profile->last_name);
            $profile->uuid = Str::uuid()->toString();
        });
    }

    public function getFullNameAttribute()
    {
         return "{$this->first_name} {$this->last_name}";
    }

    public function getFullUrlAttribute()
    {
        return "{$this->slug}-{$this->uuid}";
    }
}
