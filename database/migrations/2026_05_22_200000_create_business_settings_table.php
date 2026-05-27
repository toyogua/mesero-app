<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('business_name')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        // Inserta la única fila que existirá siempre
        DB::table('business_settings')->insert(['created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
