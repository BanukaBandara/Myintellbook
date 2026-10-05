<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScoreEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $itemId;
    public $userId;
    public $input;
    public $sourceType;
    public $sourceId;
    /**
     * Create a new event instance.
     */
    public function __construct($itemId, $userId, $sourceType,$sourceId,$input=0)
    {
        $this->itemId = $itemId;
        $this->userId = $userId;
        $this->input = $input;
        $this->sourceType = $sourceType;
        $this->sourceId = $sourceId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
