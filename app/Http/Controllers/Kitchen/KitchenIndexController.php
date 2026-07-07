<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Station;
use Inertia\Inertia;
use Inertia\Response;

class KitchenIndexController extends Controller
{
    public function index(): Response
    {
        $items = OrderItem::whereIn('status', ['firing', 'prep', 'ready'])
            ->with(['order.diningSession.restaurantTable', 'orderItemAllergens', 'station'])
            ->get();

        return Inertia::render('Kitchen/Index', [
            'initialItems' => $items,
            'stations' => Station::all(),
        ]);
    }
}
