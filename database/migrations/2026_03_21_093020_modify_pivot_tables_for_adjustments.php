<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_equipment', function (Blueprint $table) {
            $table->unsignedBigInteger('equipment_id')->nullable()->change();
            $table->string('custom_name')->nullable()->after('equipment_id')->comment('手動輸入的名稱');
        });

        Schema::table('order_material', function (Blueprint $table) {
            $table->unsignedBigInteger('material_id')->nullable()->change();
            $table->string('custom_name')->nullable()->after('material_id')->comment('手動輸入的名稱');
        });
    }

    public function down(): void
    {
        Schema::table('order_equipment', function (Blueprint $table) {
            $table->unsignedBigInteger('equipment_id')->nullable(false)->change();
            $table->dropColumn('custom_name');
        });

        Schema::table('order_material', function (Blueprint $table) {
            $table->unsignedBigInteger('material_id')->nullable(false)->change();
            $table->dropColumn('custom_name');
        });
    }
};