<?php

namespace App\Listeners;

use App\Events\ScoreEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\ScoreCalculatorService;
use App\Models\ScoringItem;
use App\Models\UserScore;
use Illuminate\Support\Facades\Log;

class ScoreListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ScoreEvent $event): void
    {
        $item = ScoringItem::find($event->itemId);
        if (!$item) {
            Log::warning('Score event ignored because its scoring item is not configured.', [
                'scoring_item_id' => $event->itemId,
                'user_id' => $event->userId,
                'source_type' => $event->sourceType,
                'source_id' => $event->sourceId,
            ]);

            return;
        }

        $service = new ScoreCalculatorService();

        $calculated = $service->calculate($item,$event->input);

        UserScore::updateOrCreate(
            [
                // 🔑 Uniqueness conditions
                'user_id'         => $event->userId,
                'scoring_item_id' => $item->id,
                'source_type'     => $event->sourceType,
                'source_id'       => $event->sourceId,
            ],
            [
                // ✏️ Values to update
                'calculated_score' => $calculated,
                'input_value'      => $event->input,
            ]
        );
    }
}
