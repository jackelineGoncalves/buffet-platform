<?php

namespace App\Http\Controllers\Diner;

use App\Exceptions\TableOccupiedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Diner\StoreDinerSessionRequest;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;

class DinerSessionController extends Controller
{
    public function store(StoreDinerSessionRequest $request, string $code): JsonResponse
    {
        $table = RestaurantTable::where('code', $code)->firstOrFail();

        try {
            $session = $table->diningSessions()->create([
                'guests' => $request->validated('guests'),
                'opened_at' => now(),
            ]);
        } catch (TableOccupiedException) {
            $existing = $table->diningSessions()
                ->where('status', '!=', 'closed')
                ->latest('opened_at')
                ->first();

            return response()->json([
                'message' => 'This table already has an active dining session.',
                'session' => $existing,
            ], 409);
        }

        return response()->json(['session' => $session], 201);
    }
}
