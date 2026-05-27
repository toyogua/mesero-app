<?php

namespace App\Services\Fel\Contracts;

use App\Services\Fel\FelResult;

interface FelAdapterInterface
{
    /**
     * Submit an unsigned DTE XML to the certificador and return the result.
     * The adapter is responsible for signing and transmission.
     */
    public function submit(string $xml, string $nit): FelResult;

    /**
     * Request annulment of a previously issued DTE by UUID.
     * Required by SAT Guatemala for legal void operations.
     */
    public function cancel(string $uuid, string $nit, string $reason): FelResult;
}
