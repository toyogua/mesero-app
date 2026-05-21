<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_modifier_group', function (Blueprint $table) {
            $table->foreignUlid('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->integer('display_order')->default(0);
            $table->primary(['menu_item_id', 'modifier_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_modifier_group');
    }
};
