<?php

namespace App\Http\Controllers;

use App\Models\Check;
use App\Models\PaymentSplit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SplitController extends Controller
{
    /**
     * Create equal splits for the check total.
     * POST /checks/{check}/splits  { parts: 2 }  → equal split
     * POST /checks/{check}/splits  { splits: [{label,amount},...] }  → custom amounts
     */
    public function store(Request $request, Check $check): RedirectResponse
    {
        $this->assertOpen($check);

        $request->validate([
            'parts'          => 'nullable|integer|min:2|max:20',
            'splits'         => 'nullable|array|min:2|max:20',
            'splits.*.label' => 'required_with:splits|string|max:80',
            'splits.*.amount'=> 'required_with:splits|numeric|min:0.01',
        ]);

        $check->splits()->delete();

        if ($request->filled('parts')) {
            $parts  = (int) $request->input('parts');
            $each   = round((float) $check->total / $parts, 2);
            $remainder = round((float) $check->total - ($each * $parts), 2);

            for ($i = 1; $i <= $parts; $i++) {
                $amount = ($i === $parts) ? $each + $remainder : $each;
                $check->splits()->create(['label' => "Parte {$i}", 'amount' => $amount]);
            }
        } else {
            foreach ($request->input('splits') as $row) {
                $check->splits()->create(['label' => $row['label'], 'amount' => $row['amount']]);
            }
        }

        return back();
    }

    public function pay(Request $request, PaymentSplit $split): RedirectResponse
    {
        $this->assertOpen($split->check);

        $request->validate(['method' => 'required|in:cash,card,transfer']);

        $split->update(['method' => $request->method, 'paid_at' => now()]);

        return back();
    }

    public function destroy(Check $check): RedirectResponse
    {
        $this->assertOpen($check);

        $check->splits()->delete();

        return back();
    }

    private function assertOpen(Check $check): void
    {
        if (! $check->status->isMutable()) {
            throw ValidationException::withMessages(['check' => 'La comanda ya no es modificable.']);
        }
    }
}
