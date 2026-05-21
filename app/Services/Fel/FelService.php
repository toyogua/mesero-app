<?php

namespace App\Services\Fel;

use App\Enums\FelStatus;
use App\Models\Check;
use App\Models\FelInvoice;
use App\Services\Fel\Adapters\InfileAdapter;
use App\Services\Fel\Adapters\NullAdapter;
use App\Services\Fel\Contracts\FelAdapterInterface;
use RuntimeException;

class FelService
{
    private FelAdapterInterface $adapter;
    private DteXmlBuilder $builder;

    public function __construct(DteXmlBuilder $builder)
    {
        $this->builder = $builder;
        $this->adapter = $this->resolveAdapter();
    }

    /**
     * Create a pending FelInvoice for a check (called at close time).
     * Does NOT submit yet — the Job does the actual submission.
     */
    public function createPending(Check $check, string $receptorNit = 'CF', string $receptorName = 'CONSUMIDOR FINAL'): FelInvoice
    {
        return FelInvoice::create([
            'check_id'       => $check->id,
            'status'         => FelStatus::Pending,
            'receptor_nit'   => $receptorNit,
            'receptor_name'  => $receptorName,
        ]);
    }

    /**
     * Submit the DTE to the certificador. Called by IssueFelInvoice job.
     * Idempotent: if already issued, returns silently.
     */
    public function issue(FelInvoice $invoice): void
    {
        if ($invoice->status === FelStatus::Issued) {
            return;
        }

        $check = $invoice->check()->with([
            'items' => fn ($q) => $q->orderBy('created_at'),
            'items.modifiers',
        ])->firstOrFail();

        $xml = $this->builder->build($check, $invoice);

        $invoice->forceFill(['xml_request' => $xml])->save();

        $result = $this->adapter->submit($xml, (string) config('restaurant.fel.emisor_nit'));

        if ($result->ok) {
            $invoice->markIssued($result->uuid, $result->serie, $result->numero, $result->xmlAuthorized);
        } else {
            $invoice->markFailed($result->error);
            throw new RuntimeException("FEL submission failed: {$result->error}");
        }
    }

    /**
     * Retry a failed invoice. Resets status to pending and re-issues.
     */
    public function retry(FelInvoice $invoice): void
    {
        if ($invoice->status->isFinal()) {
            throw new RuntimeException("Cannot retry a {$invoice->status->value} invoice.");
        }

        $invoice->forceFill(['status' => FelStatus::Pending, 'error_message' => null])->save();
        $this->issue($invoice);
    }

    private function resolveAdapter(): FelAdapterInterface
    {
        return match (config('restaurant.fel.adapter')) {
            'infile' => new InfileAdapter(),
            default  => new NullAdapter(),
        };
    }
}
