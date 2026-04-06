<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EngineeringItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            ['name' => '被覆銅管', 'spec' => '2分4分', 'unit' => '米', 'default_price' => 500],
            ['name' => '被覆銅管', 'spec' => '2分3分', 'unit' => '米', 'default_price' => 400],
            ['name' => '安裝工資', 'spec' => '3台', 'unit' => '台', 'default_price' => 3500],
            ['name' => '安裝架', 'spec' => '3組', 'unit' => '組', 'default_price' => 1500],
            ['name' => '控制線電源線', 'spec' => '1式', 'unit' => '式', 'default_price' => 1500],
            ['name' => '牆壁挖孔及修補', 'spec' => '1式', 'unit' => '式', 'default_price' => 3000],
            ['name' => '洗孔', 'spec' => '1口', 'unit' => '口', 'default_price' => 800],
            ['name' => '排水打牆及修補', 'spec' => '1口', 'unit' => '口', 'default_price' => 1500],
            ['name' => '室內管槽', 'spec' => '百合白', 'unit' => '台', 'default_price' => 3000],
            ['name' => '室外管槽', 'spec' => null, 'unit' => '式', 'default_price' => 5000],
            ['name' => '退現場評估工資', 'spec' => null, 'unit' => '式', 'default_price' => -500],
        ];

        foreach ($items as $item) {
            DB::table('engineering_items')->insert(array_merge($item, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}