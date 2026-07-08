<?php

namespace App\Events;

use App\Models\ServiceRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceRequestCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly ServiceRequest $serviceRequest) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('floor')];
    }

    public function broadcastAs(): string
    {
        return 'ServiceRequestCreated';
    }

    public function broadcastWith(): array
    {
        $this->serviceRequest->loadMissing('diningSession.restaurantTable');

        return [
            'service_request' => [
                'id' => $this->serviceRequest->id,
                'type' => $this->serviceRequest->type,
                'requested_at' => $this->serviceRequest->requested_at,
                'dining_session' => [
                    'id' => $this->serviceRequest->diningSession->id,
                    'restaurant_table' => [
                        'code' => $this->serviceRequest->diningSession->restaurantTable->code,
                    ],
                ],
            ],
        ];
    }
}
