<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Settings', [
            'setting' => Setting::first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'buffet_price' => 'required|numeric|min:0',
            'waste_fee' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:1',
            'session_minutes' => 'required|integer|min:1',
            'last_call_minutes' => 'required|integer|min:1',
        ]);

        Setting::first()->update($data);

        return back()->with('success', 'Configuración guardada.');
    }
}
