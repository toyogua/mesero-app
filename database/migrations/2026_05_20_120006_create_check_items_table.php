<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('check_id')->constrained('checks')->cascadeOnDelete();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->foreignUlid('kitchen_station_id')->constrained('kitchen_stations')->restrictOnDelete();
            $table->string('name_snapshot');
            $table->decimal('price_snapshot', 10, 2);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('notes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();

            $table->index(['check_id', 'status']);
            $table->index(['kitchen_station_id', 'status']);
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_items');
    }
};
