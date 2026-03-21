<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_equipment', function (Blueprint $table) {
            if (Schema::hasColumn('order_equipment', 'custom_name')) {
                $table->dropColumn('custom_name');
            }
            if (!Schema::hasColumn('order_equipment', 'is_adjustment')) {
                $table->boolean('is_adjustment')->default(false)->after('quantity');
            }
        });

        Schema::table('order_material', function (Blueprint $table) {
            if (Schema::hasColumn('order_material', 'custom_name')) {
                $table->dropColumn('custom_name');
            }
            if (!Schema::hasColumn('order_material', 'is_adjustment')) {
                $table->boolean('is_adjustment')->default(false)->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_equipment', function (Blueprint $table) {
            $table->dropColumn('is_adjustment');
            $table->string('custom_name')->nullable();
        });

        Schema::table('order_material', function (Blueprint $table) {
            $table->dropColumn('is_adjustment');
            $table->string('custom_name')->nullable();
        });
    }
};