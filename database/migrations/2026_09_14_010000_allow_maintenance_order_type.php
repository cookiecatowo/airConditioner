<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->allowsMaintenance()) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('type', ['install', 'repair', 'maintenance'])->default('install')->change();
        });
    }

    public function down(): void
    {
        DB::table('orders')->where('type', 'maintenance')->update(['type' => 'repair']);

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('type', ['install', 'repair'])->default('install')->change();
        });
    }

    private function allowsMaintenance(): bool
    {
        if (DB::getDriverName() !== 'sqlite') {
            return false;
        }

        $sql = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='orders'")->sql ?? '';

        return str_contains($sql, "'maintenance'");
    }
};
