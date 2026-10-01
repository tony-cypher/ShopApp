<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Pill order matches the reference design:
     * All Categories / Deals are handled in the UI, the rest are real categories.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Crypto', 'slug' => 'crypto', 'emoji' => '₿'],
            ['name' => 'Fashion', 'slug' => 'fashion', 'emoji' => '👗'],
            ['name' => 'Health & Wellness', 'slug' => 'health-wellness', 'emoji' => '🧘'],
            ['name' => 'Art', 'slug' => 'art', 'emoji' => '🎨'],
            ['name' => 'Home', 'slug' => 'home', 'emoji' => '🏠'],
            ['name' => 'Sport', 'slug' => 'sport', 'emoji' => '⚽'],
            ['name' => 'Music', 'slug' => 'music', 'emoji' => '🎵'],
            ['name' => 'Gaming', 'slug' => 'gaming', 'emoji' => '🎮'],
        ];

        foreach ($categories as $index => $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category + ['position' => $index + 1],
            );
        }
    }
}
