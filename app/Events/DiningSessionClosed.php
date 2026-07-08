<?php

namespace App\Events;

use App\Models\DiningSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiningSessionClosed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly DiningSession $session) {}

    public function broadcastOn(): array
    {
        $this->session->loadMissing('restaurantTable');

        return [new Channel('table.' . $this->session->restaurantTable->code)];
    }

    public function broadcastAs(): string
    {
        return 'DiningSessionClosed';
    }

    public function broadcastWith(): array
    {
        return ['dining_session_id' => $this->session->id];
    }
}
