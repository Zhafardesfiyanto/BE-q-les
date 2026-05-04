<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
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
            'classroom_id' => Classroom::factory(),
            'assignment_id' => null,
            'body' => $this->faker->paragraph(),
        ];
    }

    /**
     * Indicate that the message is for a classroom.
     */
    public function forClassroom(Classroom $classroom): static
    {
        return $this->state(fn (array $attributes) => [
            'classroom_id' => $classroom->id,
            'assignment_id' => null,
        ]);
    }

    /**
     * Indicate that the message is for an assignment.
     */
    public function forAssignment(Assignment $assignment): static
    {
        return $this->state(fn (array $attributes) => [
            'classroom_id' => null,
            'assignment_id' => $assignment->id,
        ]);
    }

    /**
     * Indicate that the message is from a specific user.
     */
    public function fromUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}