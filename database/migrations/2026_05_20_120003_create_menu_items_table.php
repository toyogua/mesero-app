<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('kitchen_station_id')->constrained('kitchen_stations')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('category')->index();
            $table->string('sku')->nullable()->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
