<?php

namespace App\Http\Controllers\Diner;

use App\Events\ServiceRequestCreated;
use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DinerServiceRequestController extends Controller
{
    public function store(Request $request, string $code): JsonResponse
    {
        $request->validate(['type' => 'required|in:bill,server']);

        $table = RestaurantTable::where('code', $code)->firstOrFail();

        $session = $table->diningSessions()
            ->where('status', '!=', 'closed')
            ->first();

        if (! $session) {
            return response()->json(['message' => 'No active session.'], 409);
        }

        $existing = $session->serviceRequests()
            ->where('type', $request->type)
            ->whereNull('resolved_at')
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Request already pending.'], 409);
        }

        $serviceRequest = $session->serviceRequests()->create([
            'type' => $request->type,
            'requested_at' => now(),
        ]);

        try {
            ServiceRequestCreated::dispatch($serviceRequest);
        } catch (\Throwable $e) {
            \Log::error('ServiceRequestCreated broadcast failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['service_request' => $serviceRequest], 201);
    }
}
