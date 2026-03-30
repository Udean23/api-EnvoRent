<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bundling;
use App\Models\Product;

class BundlingMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $bundling = Bundling::where('name', 'Paket Camping 2 Orang')->first();

        $tenda = Product::where('name', 'Tenda Dome 2 Orang')->first();
        $sleeping = Product::where('name', 'Sleeping Bag Polar')->first();
        $kompor = Product::where('name', 'Kompor Portable + Gas')->first();

        if ($bundling) {
            $bundling->products()->attach([
                $tenda?->id,
                $sleeping?->id,
                $kompor?->id,
            ]);
        }
    }
}