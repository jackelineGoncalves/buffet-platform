<?php

namespace App\Http\Controllers\Diner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diner\StoreDinerOrderRequest;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;

class DinerOrderController extends Controller
{
    public function store(StoreDinerOrderRequest $request, string $code): JsonResponse
    {
        $table = RestaurantTable::where('code', $code)->firstOrFail();

        $session = $table->diningSessions()
            ->where('status', '!=', 'closed')
            ->latest('opened_at')
            ->first();

        if (! $session) {
            return response()->json([
                'message' => 'This table has no active dining session.',
            ], 409);
        }

        $order = $session->placeOrder($request->validated('items'));

        return response()->json(['order' => $order], 201);
    }
}
