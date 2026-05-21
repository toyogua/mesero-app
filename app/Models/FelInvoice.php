<?php

namespace App\Models;

use App\Enums\FelStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FelInvoice extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'check_id',
        'status',
        'receptor_nit',
        'receptor_name',
        'uuid',
        'serie',
        'numero',
        'issued_at',
        'xml_request',
        'xml_authorized',
        'error_message',
        'retries',
    ];

    protected $casts = [
        'status' => FelStatus::class,
        'issued_at' => 'datetime',
        'retries' => 'integer',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    public function markIssued(string $uuid, string $serie, string $numero, string $xmlAuthorized): void
    {
        $this->forceFill([
            'status' => FelStatus::Issued,
            'uuid' => $uuid,
            'serie' => $serie,
            'numero' => $numero,
            'xml_authorized' => $xmlAuthorized,
            'issued_at' => now(),
            'error_message' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => FelStatus::Failed,
            'error_message' => $error,
            'retries' => $this->retries + 1,
        ])->save();
    }
}
