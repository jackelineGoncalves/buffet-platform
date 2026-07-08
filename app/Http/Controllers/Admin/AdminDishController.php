<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDishController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dishes', [
            'dishes' => Dish::with(['category', 'station'])->orderBy('sort_order')->orderBy('name')->get(),
            'categories' => Category::orderBy('sort_order')->get(),
            'stations' => Station::orderBy('label')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'station_id' => 'required|exists:stations,id',
            'is_extra' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'per_round_limit' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer',
        ]);

        Dish::create($data + ['is_available' => true]);

        return back();
    }

    public function update(Request $request, Dish $dish): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'station_id' => 'required|exists:stations,id',
            'is_extra' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'per_round_limit' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer',
        ]);

        $dish->update($data);

        return back();
    }

    public function toggle(Dish $dish): JsonResponse
    {
        $dish->update(['is_available' => ! $dish->is_available]);

        return response()->json(['is_available' => $dish->is_available]);
    }

    public function destroy(Dish $dish): RedirectResponse
    {
        $dish->delete();

        return back();
    }
}
