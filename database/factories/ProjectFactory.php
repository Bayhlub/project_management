<?php

namespace Database\Factories;

use App\Enums\ProjectColor;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'description' => fake()->optional()->sentence(),
            'status' => ProjectStatus::Active,
            'color' => fake()->randomElement(ProjectColor::cases()),
            'deadline' => null,
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
