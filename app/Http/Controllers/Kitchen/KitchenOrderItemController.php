<?php

namespace App\Http\Controllers\Kitchen;

use App\Events\OrderItemStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;

class KitchenOrderItemController extends Controller
{
    public function advance(OrderItem $orderItem): JsonResponse
    {
        if ($orderItem->status === 'served') {
            return response()->json(['message' => 'This item is already served.'], 422);
        }

        $next = match ($orderItem->status) {
            'firing' => 'prep',
            'prep' => 'ready',
            'ready' => 'served',
        };

        $orderItem->update(['status' => $next]);
        $orderItem->order->syncStatusFromItems();

        OrderItemStatusUpdated::dispatch($orderItem);

        return response()->json(['item' => $orderItem]);
    }
}
