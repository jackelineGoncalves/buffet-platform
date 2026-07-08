<?php

namespace App\Http\Controllers\Floor;

use App\Events\OrderItemStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;

class FloorOrderItemController extends Controller
{
    public function serve(OrderItem $orderItem): JsonResponse
    {
        if ($orderItem->status !== 'ready') {
            return response()->json(['message' => 'Item is not ready to serve.'], 422);
        }

        $orderItem->update(['status' => 'served']);
        $orderItem->order->syncStatusFromItems();

        try {
            OrderItemStatusUpdated::dispatch($orderItem);
        } catch (\Throwable $e) {
            \Log::error('OrderItemStatusUpdated broadcast failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['item' => $orderItem]);
    }
}
