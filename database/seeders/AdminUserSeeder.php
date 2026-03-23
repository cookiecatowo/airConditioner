<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'lszhan@msn.com'],
            [
                'name' => 'Lszhan',
                'password' => Hash::make('Ls822828'),
                'email_verified_at' => now(),
            ]
        );
    }
}
