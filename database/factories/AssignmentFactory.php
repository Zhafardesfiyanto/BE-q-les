<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'title' => $this->faker->words(3, true) . ' Assignment',
            'description' => $this->faker->paragraph(),
            'exam_mode' => false,
            'due_at' => $this->faker->dateTimeBetween('+1 week', '+2 weeks'),
        ];
    }

    /**
     * Indicate that the assignment is in exam mode.
     */
    public function examMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'exam_mode' => true,
        ]);
    }

    /**
     * Indicate that the assignment belongs to a specific classroom.
     */
    public function forClassroom(Classroom $classroom): static
    {
        return $this->state(fn (array $attributes) => [
            'classroom_id' => $classroom->id,
        ]);
    }
}