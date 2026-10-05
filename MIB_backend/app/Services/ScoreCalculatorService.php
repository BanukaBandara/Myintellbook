<?php

namespace App\Services;
use App\Models\ScoringItem;

class ScoreCalculatorService
{
     public function calculate(ScoringItem $item, $input)
    {
        switch($item->rule_type) {
            case 'fixed':
                return $item->base_score;

            case 'per_day':
                return $input * $item->base_score;

            case 'per_exam':
                return min($input, $item->max_score);

            case 'percentage':
                return ($input / 100) * $item->base_score;

            case 'custom':
                return $this->handleCustomLogic($item, $input);
        }
    }

    protected function handleCustomLogic($item, $input)
    {
        
    }
}