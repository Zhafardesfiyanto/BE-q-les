<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Class',
            'code' => Str::upper(Str::random(8)),
            'teacher_id' => User::factory()->create(['role' => \App\Enums\UserRole::Guru])->id,
        ];
    }

    /**
     * Indicate that the classroom belongs to a specific teacher.
     */
    public function forTeacher(User $teacher): static
    {
        return $this->state(fn (array $attributes) => [
            'teacher_id' => $teacher->id,
        ]);
    }

    /**
     * Indicate that the classroom has a specific code.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
        ]);
    }
}