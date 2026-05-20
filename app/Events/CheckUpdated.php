<?php

namespace App\Events;

use App\Models\Check;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CheckUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Check $check,
        public string $reason = 'updated',
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('check.'.$this->check->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'check_id' => $this->check->id,
            'reason' => $this->reason,
            'at' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CheckUpdated';
    }
}
