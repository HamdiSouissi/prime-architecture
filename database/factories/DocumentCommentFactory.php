<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentComment> */
class DocumentCommentFactory extends Factory
{
    public function definition(): array
    {
        return ['document_id' => Document::factory(), 'user_id' => User::factory(), 'body' => fake()->sentence(), 'is_internal' => true];
    }
}
