<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('area_id')->constrained('areas')->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('capacity')->default(4);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['area_id', 'active']);
            $table->unique(['area_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
