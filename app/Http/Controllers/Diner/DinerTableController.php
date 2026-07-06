<?php

namespace App\Http\Controllers\Diner;

use App\Http\Controllers\Controller;
use App\Models\Allergen;
use App\Models\Category;
use App\Models\RestaurantTable;
use Inertia\Inertia;
use Inertia\Response;

class DinerTableController extends Controller
{
    public function show(string $code): Response
    {
        $table = RestaurantTable::where('code', $code)->firstOrFail();

        $session = $table->diningSessions()
            ->where('status', '!=', 'closed')
            ->latest('opened_at')
            ->with('orders.orderItems.orderItemAllergens')
            ->first();

        $menu = Category::query()
            ->orderBy('sort_order')
            ->with(['dishes' => fn ($query) => $query->where('is_available', true)->with('tags')])
            ->get();

        return Inertia::render('Diner/Table', [
            'code' => $code,
            'table' => $table,
            'session' => $session,
            'menu' => $menu,
            'allergens' => Allergen::all(),
        ]);
    }
}
