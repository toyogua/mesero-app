<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_ratings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('check_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('rated_at');
            $table->timestamps();

            $table->unique('check_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ratings');
    }
};
