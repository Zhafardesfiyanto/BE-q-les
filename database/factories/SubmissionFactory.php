<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'user_id' => User::factory(),
            'status' => SubmissionStatus::Dikumpulkan,
            'screenshot_path' => $this->faker->imageUrl(),
            'gesture_log' => null,
            'total_grade' => null,
            'submitted_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Indicate that the submission is late.
     */
    public function terlambat(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubmissionStatus::Terlambat,
        ]);
    }

    /**
     * Indicate that the submission has gesture log.
     */
    public function withGestureLog(): static
    {
        return $this->state(fn (array $attributes) => [
            'gesture_log' => [
                ['type' => 'click', 'timestamp' => now()->timestamp, 'x' => 100, 'y' => 200],
                ['type' => 'scroll', 'timestamp' => now()->timestamp + 1, 'delta' => 50],
            ],
        ]);
    }

    /**
     * Indicate that the submission belongs to a specific assignment.
     */
    public function forAssignment(Assignment $assignment): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_id' => $assignment->id,
        ]);
    }

    /**
     * Indicate that the submission belongs to a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}