<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Question;
use Laravel\Scout\Searchable;

class Exam extends Model
{

    use Searchable;

    protected $fillable= ['profession_id','title','description','duration_minutes','total_marks','difficulty'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'exam_user')
                    ->withPivot('score', 'attempted_at', 'passed','answers')
                    ->withTimestamps();
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class,'exam_question');
                
    }

    public function profession()
    {
        return $this->belongsTo(\App\Models\profession::class);
    }

        /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = [
            'title'=>$this->title,
        ];
 
        // Customize the data array...
 
        return $array;
    }
}
