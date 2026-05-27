<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fel_invoices', function (Blueprint $table) {
            $table->string('cancel_reason', 255)->nullable()->after('error_message');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
        });
    }

    public function down(): void
    {
        Schema::table('fel_invoices', function (Blueprint $table) {
            $table->dropColumn(['cancel_reason', 'cancelled_at']);
        });
    }
};
