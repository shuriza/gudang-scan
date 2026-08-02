<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['barcode' => '8991002101012', 'name' => 'Indomie Goreng Dus', 'unit' => 'dus', 'location' => 'A-01', 'stock' => 40, 'min_stock' => 10],
            ['barcode' => '8992761111014', 'name' => 'Aqua 600ml Karton', 'unit' => 'karton', 'location' => 'A-02', 'stock' => 25, 'min_stock' => 8],
            ['barcode' => '8992696404021', 'name' => 'Kopi Kapal Api 165g', 'unit' => 'pcs', 'location' => 'B-01', 'stock' => 120, 'min_stock' => 30],
            ['barcode' => '8998866200011', 'name' => 'Minyak Goreng 2L', 'unit' => 'pcs', 'location' => 'B-03', 'stock' => 6, 'min_stock' => 12],
            ['barcode' => '8996001600146', 'name' => 'Gula Pasir 1kg', 'unit' => 'pcs', 'location' => 'C-01', 'stock' => 85, 'min_stock' => 20],
            ['barcode' => '8993058200016', 'name' => 'Sabun Lifebuoy 100g', 'unit' => 'pcs', 'location' => 'C-04', 'stock' => 3, 'min_stock' => 15],
        ];

        foreach ($products as $product) {
            $locationCode = $product['location'];
            unset($product['location']);

            $location = Location::firstOrCreate(
                ['code' => $locationCode],
                ['name' => "Rak {$locationCode}"],
            );

            Product::updateOrCreate(
                ['barcode' => $product['barcode']],
                [...$product, 'location_id' => $location->id],
            );
        }
    }
}
