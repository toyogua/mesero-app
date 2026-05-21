<?php

namespace App\Services\Fel;

/**
 * Value object returned by every FEL adapter.
 * On success: ok=true, uuid/serie/numero/xmlAuthorized are populated.
 * On failure: ok=false, error is populated.
 */
final class FelResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $uuid = '',
        public readonly string $serie = '',
        public readonly string $numero = '',
        public readonly string $xmlAuthorized = '',
        public readonly string $error = '',
    ) {}

    public static function success(
        string $uuid,
        string $serie,
        string $numero,
        string $xmlAuthorized,
    ): self {
        return new self(ok: true, uuid: $uuid, serie: $serie, numero: $numero, xmlAuthorized: $xmlAuthorized);
    }

    public static function failure(string $error): self
    {
        return new self(ok: false, error: $error);
    }
}
