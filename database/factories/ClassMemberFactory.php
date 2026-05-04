<?php

namespace Database\Factories;

use App\Models\ClassMember;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassMember>
 */
class ClassMemberFactory extends Factory
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
            'user_id' => User::factory(),
            'joined_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the class member belongs to a specific classroom.
     */
    public function forClassroom(Classroom $classroom): static
    {
        return $this->state(fn (array $attributes) => [
            'classroom_id' => $classroom->id,
        ]);
    }

    /**
     * Indicate that the class member is a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}