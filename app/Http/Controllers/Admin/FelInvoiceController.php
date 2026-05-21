<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\IssueFelInvoice;
use App\Models\FelInvoice;
use App\Services\Fel\FelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FelInvoiceController extends Controller
{
    public function index(): Response
    {
        $invoices = FelInvoice::with('check:id,number,total,closed_at')
            ->latest()
            ->paginate(50)
            ->through(fn ($inv) => [
                'id'            => $inv->id,
                'check_number'  => $inv->check?->number,
                'check_total'   => (float) $inv->check?->total,
                'status'        => $inv->status->value,
                'uuid'          => $inv->uuid,
                'serie'         => $inv->serie,
                'numero'        => $inv->numero,
                'receptor_nit'  => $inv->receptor_nit,
                'error_message' => $inv->error_message,
                'retries'       => $inv->retries,
                'issued_at'     => $inv->issued_at?->toIso8601String(),
                'created_at'    => $inv->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/FelInvoices/Index', [
            'invoices' => $invoices,
            'fel_enabled' => (bool) config('restaurant.fel.enabled'),
        ]);
    }

    public function retry(FelInvoice $invoice, FelService $fel): RedirectResponse
    {
        if ($invoice->status->isFinal()) {
            throw ValidationException::withMessages([
                'invoice' => "No se puede reintentar una factura {$invoice->status->value}.",
            ]);
        }

        IssueFelInvoice::dispatch($invoice);

        return back()->with('success', 'Reintento programado.');
    }
}
