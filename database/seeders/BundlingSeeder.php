<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bundling;
use App\Models\Category;

class BundlingSeeder extends Seeder
{
    public function run(): void
    {
        $tendaCategory = Category::where('name', 'Tenda')->first();

        Bundling::create([
            'name' => 'Paket Camping 2 Orang',
            'description' => 'Include: Tenda 2P, 2 Sleeping Bag, 2 Matras, Kompor Portable.',
            'price' => 120000,
            'image' => null,
            'category_id' => $tendaCategory?->id,
        ]);

        Bundling::create([
            'name' => 'Paket Pendakian Hemat',
            'description' => 'Include: Carrier 60L, Sleeping Bag, Headlamp.',
            'price' => 90000,
            'image' => null,
            'category_id' => $tendaCategory?->id,
        ]);

        Bundling::create([
            'name' => 'Paket Family Camping',
            'description' => 'Include: Tenda 4P, 4 Sleeping Bag, Cooking Set, Lampu Camping.',
            'price' => 200000,
            'image' => null,
            'category_id' => $tendaCategory?->id,
        ]);
    }
}