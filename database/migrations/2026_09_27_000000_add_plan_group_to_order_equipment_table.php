<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_equipment', 'plan_group')) {
            Schema::table('order_equipment', function (Blueprint $table) {
                $table->string('plan_group')->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'plan_undecided')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('plan_undecided')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::table('order_equipment', function (Blueprint $table) {
            $table->dropColumn('plan_group');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('plan_undecided');
        });
    }
};
