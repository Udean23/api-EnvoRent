<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $tenda = Category::where('name', 'Tenda')->first();
        $carrier = Category::where('name', 'Tas & Carrier')->first();
        $sleeping = Category::where('name', 'Sleeping Gear')->first();
        $masak = Category::where('name', 'Peralatan Masak')->first();

        Product::create([
            'name' => 'Tenda Dome 2 Orang',
            'description' => 'Tenda kapasitas 2 orang, tahan air dan angin.',
            'price' => 50000,
            'image' => null,
            'category_id' => $tenda?->id,
        ]);

        Product::create([
            'name' => 'Carrier 60L',
            'description' => 'Tas gunung kapasitas 60 liter, cocok untuk 2-3 hari pendakian.',
            'price' => 40000,
            'image' => null,
            'category_id' => $carrier?->id,
        ]);

        Product::create([
            'name' => 'Sleeping Bag Polar',
            'description' => 'Sleeping bag hangat untuk suhu dingin.',
            'price' => 25000,
            'image' => null,
            'category_id' => $sleeping?->id,
        ]);

        Product::create([
            'name' => 'Kompor Portable + Gas',
            'description' => 'Kompor portable ringan untuk camping.',
            'price' => 30000,
            'image' => null,
            'category_id' => $masak?->id,
        ]);
    }
}