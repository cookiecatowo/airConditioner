<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 執行遷移：建立報價單資料表
     */
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');    // 客戶姓名
            $table->string('customer_phone');   // 客戶電話
            $table->string('install_address');  // 安裝地址
            $table->string('ac_model');         // 冷氣型號 (大金、日立等)
            $table->integer('price');           // 單價
            $table->integer('quantity')->default(1); // 數量 (預設 1 台)
            $table->integer('total_amount');    // 總金額
            $table->text('notes')->nullable();  // 備註 (例如：洗孔、架子費用)
            $table->timestamps();               // 自動記錄建立時間
        });
    }

    /**
     * 復原遷移
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};