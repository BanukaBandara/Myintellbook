<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserScore extends Model
{
    protected $fillable=[
        'user_id',
        'scoring_item_id',
        'calculated_score',
        'input_value',
        'source_type',
        'source_id'
    ];

      public function scoreItem()
    {
        return $this->belongsTo(\App\Models\ScoringItem::class);
    }
    
     public function source()
    {
        return $this->morphTo();
    }
}
