<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price_delta', 8, 2)->default(0);
            $table->integer('display_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_options');
    }
};
