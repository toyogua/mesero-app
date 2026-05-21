<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fel_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('check_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('status', 20)->default('pending'); // pending|issued|failed|cancelled

            // Receptor data (captured at close time)
            $table->string('receptor_nit', 20)->default('CF');
            $table->string('receptor_name', 200)->default('CONSUMIDOR FINAL');

            // SAT authorization data (filled on success)
            $table->string('uuid', 100)->nullable();
            $table->string('serie', 20)->nullable();
            $table->string('numero', 20)->nullable();
            $table->timestamp('issued_at')->nullable();

            // Payload round-trip
            $table->longText('xml_request')->nullable();
            $table->longText('xml_authorized')->nullable();

            // Error tracking
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retries')->default(0);

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fel_invoices');
    }
};
