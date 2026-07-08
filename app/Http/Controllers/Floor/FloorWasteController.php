<?php

namespace App\Http\Controllers\Floor;

use App\Http\Controllers\Controller;
use App\Models\DiningSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloorWasteController extends Controller
{
    public function update(Request $request, DiningSession $session): JsonResponse
    {
        $request->validate(['delta' => 'required|in:-1,1']);

        $newCount = max(0, $session->waste_count + $request->delta);
        $session->update(['waste_count' => $newCount]);

        return response()->json(['waste_count' => $newCount]);
    }
}
