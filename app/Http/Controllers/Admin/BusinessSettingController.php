<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSettingController extends Controller
{
    public function edit(): Response
    {
        $s = BusinessSetting::instance();

        return Inertia::render('Admin/BusinessSettings/Edit', [
            'settings' => [
                'business_name'               => $s->business_name,
                'address'                     => $s->address,
                'phone'                       => $s->phone,
                'logo_url'                    => $s->logo_url,
                'display_pin'                 => $s->display_pin,
                'online_ordering_enabled'     => (bool) ($s->online_ordering_enabled ?? true),
                'online_ordering_min_amount'  => $s->online_ordering_min_amount ? (float) $s->online_ordering_min_amount : null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name'              => 'nullable|string|max:120',
            'address'                    => 'nullable|string|max:255',
            'phone'                      => 'nullable|string|max:30',
            'display_pin'                => 'nullable|string|max:10',
            'logo'                       => 'nullable|image|max:2048',
            'online_ordering_min_amount' => 'nullable|numeric|min:0',
        ]);

        $s = BusinessSetting::instance();

        $s->business_name              = $data['business_name'] ?? $s->business_name;
        $s->address                    = $data['address']       ?? $s->address;
        $s->phone                      = $data['phone']         ?? $s->phone;
        $s->display_pin                = $data['display_pin'] ?: null;
        $s->online_ordering_enabled    = $request->boolean('online_ordering_enabled');
        $s->online_ordering_min_amount = $data['online_ordering_min_amount'] ?: null;

        if ($request->hasFile('logo')) {
            if ($s->logo_path) {
                Storage::disk('public')->delete($s->logo_path);
            }
            $s->logo_path = $request->file('logo')->store('logo', 'public');
        }

        $s->save();

        return back()->with('success', 'Configuración guardada.');
    }

    public function deleteLogo(): RedirectResponse
    {
        $s = BusinessSetting::instance();

        if ($s->logo_path) {
            Storage::disk('public')->delete($s->logo_path);
            $s->update(['logo_path' => null]);
        }

        return back()->with('success', 'Logo eliminado.');
    }
}
