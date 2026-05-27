<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modifier_option_ingredients')) {
            return;
        }

        Schema::create('modifier_option_ingredients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('modifier_option_id')->constrained('modifier_options')->cascadeOnDelete();
            $table->foreignUlid('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('quantity_used', 10, 4);
            $table->timestamps();

            $table->unique(['modifier_option_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_option_ingredients');
    }
};
