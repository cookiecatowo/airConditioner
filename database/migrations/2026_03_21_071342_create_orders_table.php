<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('address')->nullable()->comment('施工地址');
            $table->date('date')->nullable()->comment('施工或報價日期');
            $table->enum('type', ['install', 'repair'])->default('install')->comment('裝機 or 維修');
            $table->decimal('total_amount', 12, 2)->default(0)->comment('總價');
            $table->text('notes')->nullable()->comment('備註');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};