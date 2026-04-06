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
        Schema::create('engineering_items', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('工程名稱'); 
            $table->string('spec')->nullable()->comment('規格'); 
            $table->string('unit')->comment('單位'); 
            $table->integer('default_price')->comment('預設單價');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engineering_items');
    }
};