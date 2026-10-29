<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'category_id' => DocumentCategory::factory(), 'created_by' => User::factory(), 'title' => fake()->sentence(), 'visible_to_client' => false];
    }
}
