<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_used', 10, 4);
            $table->timestamps();

            $table->unique(['menu_item_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
    }
};
