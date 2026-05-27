<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->string('order_type')->default('dine_in')->after('status');
            $table->string('customer_name')->nullable()->after('transferred_from');
            $table->string('customer_phone', 20)->nullable()->after('customer_name');
            $table->string('customer_address')->nullable()->after('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn(['order_type', 'customer_name', 'customer_phone', 'customer_address']);
        });
    }
};
