<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Supplier Utama', 'phone' => '081234567890'],
            ['name' => 'Distributor Lokal', 'phone' => '081298765432'],
        ] as $supplier) {
            Supplier::firstOrCreate(['name' => $supplier['name']], $supplier);
        }
    }
}
