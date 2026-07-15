<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Hot Wheels Basic', 'description' => 'Koleksi Hot Wheels reguler', 'sort_order' => 1],
            ['name' => 'Hot Wheels Premium', 'description' => 'Seri premium dengan detail tinggi', 'sort_order' => 2],
            ['name' => 'Collector Edition', 'description' => 'Edisi kolektor terbatas', 'sort_order' => 3],
            ['name' => 'Treasure Hunt', 'description' => 'Seri Treasure Hunt langka', 'sort_order' => 4],
            ['name' => 'Super Treasure Hunt', 'description' => 'Super Treasure Hunt sangat langka', 'sort_order' => 5],
            ['name' => 'Car Culture', 'description' => 'Seri Car Culture', 'sort_order' => 6],
            ['name' => 'Team Transport', 'description' => 'Set Team Transport', 'sort_order' => 7],
            ['name' => 'Diorama Sets', 'description' => 'Set diorama dan playset', 'sort_order' => 8],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(
                ['slug' => Str::slug($cat['name'])],
                array_merge($cat, ['is_active' => true])
            );
        }
    }
}
