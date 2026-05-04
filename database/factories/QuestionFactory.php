<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Assignment;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Question>
 */
class QuestionFactory extends Factory
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
            'body' => $this->faker->sentence(),
            'type' => QuestionType::PilihanGanda,
            'options' => [
                ['text' => $this->faker->sentence(), 'is_correct' => true],
                ['text' => $this->faker->sentence(), 'is_correct' => false],
                ['text' => $this->faker->sentence(), 'is_correct' => false],
                ['text' => $this->faker->sentence(), 'is_correct' => false],
            ],
            'weight' => $this->faker->randomFloat(2, 1, 10),
            'order' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Indicate that the question is multiple choice.
     */
    public function pilihanGanda(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuestionType::PilihanGanda,
            'options' => [
                ['text' => $this->faker->sentence(), 'is_correct' => true],
                ['text' => $this->faker->sentence(), 'is_correct' => false],
                ['text' => $this->faker->sentence(), 'is_correct' => false],
                ['text' => $this->faker->sentence(), 'is_correct' => false],
            ],
        ]);
    }

    /**
     * Indicate that the question is complex multiple choice.
     */
    public function pilihanGandaKompleks(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuestionType::PilihanGandaKompleks,
            'options' => [
                ['text' => $this->faker->sentence(), 'is_correct' => true],
                ['text' => $this->faker->sentence(), 'is_correct' => true],
                ['text' => $this->faker->sentence(), 'is_correct' => false],
                ['text' => $this->faker->sentence(), 'is_correct' => false],
            ],
        ]);
    }

    /**
     * Indicate that the question is essay.
     */
    public function uraian(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuestionType::Uraian,
            'options' => null,
        ]);
    }

    /**
     * Indicate that the question belongs to a specific assignment.
     */
    public function forAssignment(Assignment $assignment): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_id' => $assignment->id,
        ]);
    }
}