<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->tinyInteger('processing_status')->default(1)->comment('1=處理中 2=已完成 3=垃圾桶')->after('notes');
            $table->tinyInteger('payment_status')->default(1)->comment('1=未收款 2=已收訂金 3=已結清')->after('processing_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['processing_status', 'payment_status']);
        });
    }
};
