<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockAlert implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $ingredients,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('display')];
    }

    public function broadcastWith(): array
    {
        return ['ingredients' => $this->ingredients];
    }

    public function broadcastAs(): string
    {
        return 'StockAlert';
    }
}
