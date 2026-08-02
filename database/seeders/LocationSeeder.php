<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'A-01', 'name' => 'Rak A-01'],
            ['code' => 'A-02', 'name' => 'Rak A-02'],
            ['code' => 'B-01', 'name' => 'Rak B-01'],
            ['code' => 'B-03', 'name' => 'Rak B-03'],
            ['code' => 'C-01', 'name' => 'Rak C-01'],
            ['code' => 'C-04', 'name' => 'Rak C-04'],
        ] as $location) {
            Location::firstOrCreate(['code' => $location['code']], $location);
        }
    }
}
