<?php

namespace App\Services\Fel\Adapters;

use App\Services\Fel\Contracts\FelAdapterInterface;
use App\Services\Fel\FelResult;
use Illuminate\Support\Str;

/**
 * No-op adapter for local development and tests.
 * Always succeeds immediately with a fake UUID.
 */
class NullAdapter implements FelAdapterInterface
{
    public function submit(string $xml, string $nit): FelResult
    {
        return FelResult::success(
            uuid: Str::uuid()->toString(),
            serie: 'A',
            numero: (string) random_int(1, 99999),
            xmlAuthorized: $xml,
        );
    }

    public function cancel(string $uuid, string $nit, string $reason): FelResult
    {
        return FelResult::success(uuid: $uuid, serie: '', numero: '', xmlAuthorized: '');
    }
}
