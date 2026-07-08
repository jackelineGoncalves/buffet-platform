<?php

namespace App\Http\Controllers\Floor;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;

class FloorServiceRequestController extends Controller
{
    public function resolve(ServiceRequest $serviceRequest): JsonResponse
    {
        if ($serviceRequest->resolved_at !== null) {
            return response()->json(['message' => 'Service request already resolved.'], 422);
        }

        $serviceRequest->update(['resolved_at' => now()]);

        return response()->json(['service_request' => $serviceRequest]);
    }
}
