<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_equipment', function (Blueprint $table) {
            if (!Schema::hasColumn('order_equipment', 'custom_model_name')) {
                $table->string('custom_model_name')->nullable()->after('equipment_id')->comment('手動輸入的設備型號');
            }
        });

        Schema::table('order_material', function (Blueprint $table) {
            if (!Schema::hasColumn('order_material', 'custom_name')) {
                $table->string('custom_name')->nullable()->after('material_id')->comment('手動輸入的材料名稱');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_equipment', function (Blueprint $table) {
            $table->dropColumn('custom_model_name');
        });

        Schema::table('order_material', function (Blueprint $table) {
            $table->dropColumn('custom_name');
        });
    }
};
