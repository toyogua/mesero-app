<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('number')->unique();
            $table->foreignUlid('table_id')->nullable()->constrained('tables')->restrictOnDelete();
            $table->foreignId('waiter_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('open');
            $table->unsignedSmallInteger('covers')->default(1);
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('tip', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'opened_at']);
            $table->index(['table_id', 'status']);
            $table->index('waiter_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
