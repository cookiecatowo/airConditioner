<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->string('model_name')->comment('型號/名稱');
            $table->string('specs')->nullable()->comment('規格/噸數');
            $table->decimal('default_cost_price', 10, 2)->nullable()->comment('預設進貨金額');
            $table->decimal('default_sale_price', 10, 2)->nullable()->comment('預設賣出金額');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipments');
    }
};