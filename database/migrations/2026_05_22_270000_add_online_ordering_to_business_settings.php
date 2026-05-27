<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('online_ordering_enabled')->default(true)->after('display_pin');
            $table->decimal('online_ordering_min_amount', 10, 2)->nullable()->after('online_ordering_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['online_ordering_enabled', 'online_ordering_min_amount']);
        });
    }
};
