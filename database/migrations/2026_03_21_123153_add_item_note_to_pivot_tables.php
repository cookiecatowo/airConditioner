<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_equipment', function (Blueprint $table) {
            $table->string('item_note')->nullable()->comment('項目備註');
        });

        Schema::table('order_material', function (Blueprint $table) {
            $table->string('item_note')->nullable()->comment('項目備註');
        });
    }

    public function down(): void
    {
        Schema::table('order_equipment', function (Blueprint $table) {
            $table->dropColumn('item_note');
        });

        Schema::table('order_material', function (Blueprint $table) {
            $table->dropColumn('item_note');
        });
    }
};