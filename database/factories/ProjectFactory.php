<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return ['reference' => fake()->unique()->uuid(), 'client_id' => Client::factory(), 'name' => fake()->sentence(3), 'project_type' => ProjectType::Villa, 'status' => ProjectStatus::Sketch, 'lead_architect_id' => User::factory()];
    }
}
