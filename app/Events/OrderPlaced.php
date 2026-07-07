<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('kitchen')];
    }

    public function broadcastAs(): string
    {
        return 'OrderPlaced';
    }

    public function broadcastWith(): array
    {
        $this->order->loadMissing(
            'orderItems.orderItemAllergens',
            'orderItems.station',
            'diningSession.restaurantTable',
        );

        return ['order' => $this->order->toArray()];
    }
}
