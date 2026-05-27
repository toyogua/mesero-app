<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Check;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RatingController extends Controller
{
    public function show(Request $request, Check $check): Response
    {
        if (! $request->hasValidSignature()) {
            return Inertia::render('Rate', ['invalid' => true]);
        }

        $business = BusinessSetting::instance();

        // Reconstruct the signed POST URL from the current request's query string
        $postUrl = url("/rate/{$check->id}") . '?' . http_build_query($request->only('signature', 'expires'));

        return Inertia::render('Rate', [
            'check' => [
                'id'         => $check->id,
                'number'     => $check->number,
                'order_type' => $check->order_type?->value ?? 'dine_in',
            ],
            'business'      => ['name' => $business->business_name ?: config('app.name')],
            'already_rated' => $check->rating()->exists(),
            'post_url'      => $postUrl,
        ]);
    }

    public function store(Request $request, Check $check): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        if ($check->rating()->exists()) {
            return redirect()->back();
        }

        $data = $request->validate([
            'stars'   => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:500',
        ]);

        $check->rating()->create([
            'stars'      => $data['stars'],
            'comment'    => $data['comment'] ?? null,
            'ip_address' => $request->ip(),
            'rated_at'   => now(),
        ]);

        return redirect()->back();
    }
}
