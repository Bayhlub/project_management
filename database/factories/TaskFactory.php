<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => rtrim(fake()->sentence(4), '.'),
            'notes' => fake()->optional(0.3)->sentence(),
            'priority' => fake()->randomElement(Priority::cases()),
            'due_date' => null,
            'due_time' => null,
            'completed_at' => null,
        ];
    }

    public function dueOn(?string $date, ?string $time = null): static
    {
        return $this->state(fn () => ['due_date' => $date, 'due_time' => $time]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }
}
