<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_selections')->default(0)->after('display_order');
            $table->unsignedTinyInteger('max_selections')->nullable()->after('min_selections');
        });

        // Migrate existing data
        // single + required   → min=1, max=1
        // single + optional   → min=0, max=1
        // multi  + required   → min=1, max=null
        // multi  + optional   → min=0, max=null
        DB::table('modifier_groups')->update([
            'min_selections' => DB::raw("CASE WHEN `required` = 1 THEN 1 ELSE 0 END"),
            'max_selections' => DB::raw("CASE WHEN `selection_type` = 'single' THEN 1 ELSE NULL END"),
        ]);

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropColumn(['selection_type', 'required']);
        });
    }

    public function down(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->string('selection_type')->default('single')->after('name');
            $table->boolean('required')->default(false)->after('selection_type');
        });

        DB::table('modifier_groups')->update([
            'required'       => DB::raw("CASE WHEN min_selections >= 1 THEN 1 ELSE 0 END"),
            'selection_type' => DB::raw("CASE WHEN max_selections = 1 THEN 'single' ELSE 'multi' END"),
        ]);

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropColumn(['min_selections', 'max_selections']);
        });
    }
};
