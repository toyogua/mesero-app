<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            $table->decimal('quantity_before', 10, 4)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            $table->dropColumn('quantity_before');
        });
    }
};
