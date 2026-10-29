<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return ['type' => 'individual', 'name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'phone' => '+216'.fake()->numerify('########')];
    }
}
