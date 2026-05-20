<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FloorChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $areaId,
        public string $reason = 'changed',
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('floor.'.$this->areaId),
            new PrivateChannel('floor.all'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'area_id' => $this->areaId,
            'reason' => $this->reason,
            'at' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'FloorChanged';
    }
}
