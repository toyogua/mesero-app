<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_item_modifiers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('check_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('modifier_option_id')->constrained()->cascadeOnDelete();
            $table->string('name_snapshot');
            $table->decimal('price_snapshot', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_item_modifiers');
    }
};
