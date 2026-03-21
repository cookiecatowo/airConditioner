<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('姓名');
            $table->string('phone')->index()->nullable()->comment('電話(用於搜尋)');
            $table->string('address')->nullable()->comment('預設地址');
            $table->date('created_date')->nullable()->comment('建立日期(或第一次建檔日期)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};