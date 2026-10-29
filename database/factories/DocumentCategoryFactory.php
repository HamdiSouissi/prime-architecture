<?php

namespace Database\Factories;

use App\Models\DocumentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentCategory> */
class DocumentCategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['slug' => fake()->unique()->slug(), 'name_fr' => fake()->word(), 'name_ar' => 'وثائق', 'sort_order' => 0, 'is_active' => true];
    }
}
