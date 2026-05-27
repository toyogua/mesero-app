<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenQueueChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int,string>  $stationCodes  Estaciones afectadas
     */
    public function __construct(
        public array $stationCodes,
        public string $reason = 'changed',
    ) {
    }

    public function broadcastOn(): array
    {
        $private = array_map(
            fn (string $code) => new PrivateChannel('kitchen.'.$code),
            array_values(array_unique($this->stationCodes)),
        );

        return [...$private, new Channel('display')];
    }

    public function broadcastWith(): array
    {
        return [
            'reason' => $this->reason,
            'at' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'KitchenQueueChanged';
    }
}
