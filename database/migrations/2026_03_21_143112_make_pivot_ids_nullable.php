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
        });

        Schema::table('order_material', function (Blueprint $table) {
            $table->unsignedBigInteger('material_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_equipment', function (Blueprint $table) {
            $table->unsignedBigInteger('equipment_id')->nullable(false)->change();
        });

        Schema::table('order_material', function (Blueprint $table) {
            $table->unsignedBigInteger('material_id')->nullable(false)->change();
        });
    }
};