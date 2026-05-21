<?php

namespace App\Services\Fel\Contracts;

use App\Services\Fel\FelResult;

interface FelAdapterInterface
{
    /**
     * Submit an unsigned DTE XML to the certificador and return the result.
     * The adapter is responsible for signing and transmission.
     *
     * @param  string  $xml  The DTE XML built by DteXmlBuilder
     * @param  string  $nit  The emisor NIT (used for routing on multi-tenant setups)
     */
    public function submit(string $xml, string $nit): FelResult;
}
