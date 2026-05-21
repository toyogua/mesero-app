<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_closes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('period_from');
            $table->timestamp('period_to');
            $table->unsignedInteger('checks_count')->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('tip', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('cash_counted', 12, 2)->nullable()->comment('Efectivo contado manualmente');
            $table->decimal('card_counted', 12, 2)->nullable()->comment('Total tarjetas + transferencias');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closes');
    }
};
