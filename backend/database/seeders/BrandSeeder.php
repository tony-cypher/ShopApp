<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'Adidas', 'slug' => 'adidas', 'initials' => 'AD', 'color' => '#111827'],
            ['name' => 'Asics', 'slug' => 'asics', 'initials' => 'AS', 'color' => '#1d4ed8'],
            ['name' => 'Columbia', 'slug' => 'columbia', 'initials' => 'CO', 'color' => '#2563eb'],
            ['name' => 'Demix', 'slug' => 'demix', 'initials' => 'DM', 'color' => '#0d9488'],
            ['name' => 'New Balance', 'slug' => 'new-balance', 'initials' => 'NB', 'color' => '#dc2626'],
            ['name' => 'Nike', 'slug' => 'nike', 'initials' => 'NK', 'color' => '#f97316'],
            ['name' => 'Under Armour', 'slug' => 'under-armour', 'initials' => 'UA', 'color' => '#475569'],
            ['name' => 'Wilson', 'slug' => 'wilson', 'initials' => 'WI', 'color' => '#e11d48'],
            ['name' => 'Xiaomi', 'slug' => 'xiaomi', 'initials' => 'XI', 'color' => '#ff6900'],
            ['name' => 'Yonex', 'slug' => 'yonex', 'initials' => 'YO', 'color' => '#16a34a'],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(['slug' => $brand['slug']], $brand);
        }
    }
}
