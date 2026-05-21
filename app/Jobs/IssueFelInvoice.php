<?php

namespace App\Jobs;

use App\Models\FelInvoice;
use App\Services\Fel\FelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class IssueFelInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry 3 times before marking as permanently failed */
    public int $tries = 3;

    /** Backoff in seconds between retries: 60s, 300s, 600s */
    public array $backoff = [60, 300, 600];

    public function __construct(public readonly FelInvoice $invoice) {}

    public function handle(FelService $service): void
    {
        $service->issue($this->invoice);
    }

    public function failed(Throwable $e): void
    {
        // All retries exhausted — the markFailed call in FelService already set
        // the status, but we ensure it's persisted with the final error here.
        $this->invoice->markFailed($e->getMessage());
    }
}
