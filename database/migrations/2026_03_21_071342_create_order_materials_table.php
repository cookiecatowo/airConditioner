<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->decimal('unit_price', 10, 2)->default(0)->comment('當時單價');
            $table->integer('quantity')->default(1)->comment('數量');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_material');
    }
};