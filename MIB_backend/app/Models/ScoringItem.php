<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoringItem extends Model
{
    protected $fllable=[
        'name',
        'description',
        'base_score',
        'rule_type',
        'max_score',
        'min_score',
        'is_active'
    ];

     public function scores()
    {
        return $this->hasMany(\App\Models\UserScore::class, 'scoring_item_id');
    }
}
