<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Admin User
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Create Brands
        $brands = [
            ['name' => 'Nike', 'slug' => 'nike'],
            ['name' => 'Adidas', 'slug' => 'adidas'],
            ['name' => 'Puma', 'slug' => 'puma'],
            ['name' => 'Samsung', 'slug' => 'samsung'],
            ['name' => 'Apple', 'slug' => 'apple'],
        ];

        foreach ($brands as $brand) {
            Brand::create(array_merge($brand, ['is_active' => true]));
        }

        // Create Attributes
        $sizeAttribute = Attribute::create([
            'name' => 'Size',
            'slug' => 'size',
            'type' => 'select',
            'is_filterable' => true,
        ]);

        $colorAttribute = Attribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
            'is_filterable' => true,
        ]);

        // Create Attribute Values for Size
        $shoeSizes = ['6', '7', '8', '9', '10', '11'];
        foreach ($shoeSizes as $size) {
            AttributeValue::create([
                'attribute_id' => $sizeAttribute->id,
                'value' => $size,
                'display_value' => $size,
            ]);
        }

        $clothingSizes = ['S', 'M', 'L', 'XL', 'XXL'];
        foreach ($clothingSizes as $size) {
            AttributeValue::create([
                'attribute_id' => $sizeAttribute->id,
                'value' => $size,
                'display_value' => $size,
            ]);
        }

        // Create Attribute Values for Color
        $colors = [
            ['value' => 'black', 'display_value' => 'Black', 'color_code' => '#000000'],
            ['value' => 'white', 'display_value' => 'White', 'color_code' => '#FFFFFF'],
            ['value' => 'red', 'display_value' => 'Red', 'color_code' => '#FF0000'],
            ['value' => 'blue', 'display_value' => 'Blue', 'color_code' => '#0000FF'],
            ['value' => 'green', 'display_value' => 'Green', 'color_code' => '#008000'],
        ];

        foreach ($colors as $color) {
            AttributeValue::create([
                'attribute_id' => $colorAttribute->id,
                'value' => $color['value'],
                'display_value' => $color['display_value'],
                'color_code' => $color['color_code'],
            ]);
        }

        // Create Categories
        $shoesCategory = Category::create([
            'name' => 'Shoes',
            'slug' => 'shoes',
            'is_active' => true,
        ]);

        $clothesCategory = Category::create([
            'name' => 'Clothes',
            'slug' => 'clothes',
            'is_active' => true,
        ]);

        $electronicsCategory = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        // Attach attributes to categories
        $shoesCategory->attributes()->attach([
            $sizeAttribute->id => ['is_required' => true, 'sort_order' => 1],
            $colorAttribute->id => ['is_required' => false, 'sort_order' => 2],
        ]);

        $clothesCategory->attributes()->attach([
            $sizeAttribute->id => ['is_required' => true, 'sort_order' => 1],
            $colorAttribute->id => ['is_required' => false, 'sort_order' => 2],
        ]);
    }
}

