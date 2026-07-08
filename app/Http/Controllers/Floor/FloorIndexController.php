<?php

namespace App\Http\Controllers\Floor;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\Station;
use Inertia\Inertia;
use Inertia\Response;

class FloorIndexController extends Controller
{
    public function index(): Response
    {
        $readyItems = OrderItem::where('status', 'ready')
            ->with(['order.diningSession.restaurantTable', 'station'])
            ->get();

        $serviceRequests = ServiceRequest::whereNull('resolved_at')
            ->with(['diningSession.restaurantTable', 'diningSession.orders.orderItems'])
            ->orderBy('requested_at')
            ->get();

        return Inertia::render('Floor/Index', [
            'initialReadyItems' => $readyItems,
            'initialServiceRequests' => $serviceRequests,
            'setting' => Setting::first(),
        ]);
    }
}
