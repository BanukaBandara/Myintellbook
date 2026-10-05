<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class profession extends Model
{
    use Searchable;

    protected $fillable=['category_id','name']; 

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class,'profession_user');
    }
 
    public function exams()
    {
        return $this->hasMany(\App\Models\Exam::class);
    }


    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }

        /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = [
            'name'=>$this->name,
        ];
 
        // Customize the data array...
 
        return $array;
    }
}
