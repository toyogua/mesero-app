<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('purchase_unit')->nullable()->after('unit');
            $table->decimal('units_per_package', 10, 4)->nullable()->after('purchase_unit');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['purchase_unit', 'units_per_package']);
        });
    }
};
