<?php

namespace App\Http\Controllers\Floor;

use App\Events\DiningSessionClosed;
use App\Http\Controllers\Controller;
use App\Models\DiningSession;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class FloorPaymentController extends Controller
{
    public function store(DiningSession $session): JsonResponse
    {
        if ($session->status === 'closed') {
            return response()->json(['message' => 'Session already closed.'], 422);
        }

        $setting = Setting::first();

        $session->loadMissing('orders.orderItems');

        $allItems = $session->orders->flatMap(fn ($order) => $order->orderItems);

        $extrasTotal = $allItems
            ->filter(fn ($item) => $item->unit_price !== null)
            ->sum(fn ($item) => $item->unit_price * $item->qty);

        $buffetTotal = $setting->buffet_price * $session->guests;
        $wasteTotal = $setting->waste_fee * $session->waste_count;
        $subtotal = $buffetTotal + $extrasTotal + $wasteTotal;
        $tax = round($subtotal * $setting->tax_rate, 2);
        $total = $subtotal + $tax;

        $payment = $session->payment()->create([
            'guests' => $session->guests,
            'buffet_total' => $buffetTotal,
            'extras_total' => $extrasTotal,
            'waste_total' => $wasteTotal,
            'tax' => $tax,
            'total' => $total,
            'paid_at' => now(),
        ]);

        $session->serviceRequests()
            ->where('type', 'bill')
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        $session->update(['status' => 'closed']);

        try {
            DiningSessionClosed::dispatch($session);
        } catch (\Throwable $e) {
            \Log::error('DiningSessionClosed broadcast failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['payment' => $payment]);
    }
}
