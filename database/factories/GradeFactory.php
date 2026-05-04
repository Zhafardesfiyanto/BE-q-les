<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Question;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Grade>
 */
class GradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'question_id' => Question::factory(),
            'score' => $this->faker->randomFloat(2, 0, 10),
            'feedback' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the grade belongs to a specific submission.
     */
    public function forSubmission(Submission $submission): static
    {
        return $this->state(fn (array $attributes) => [
            'submission_id' => $submission->id,
        ]);
    }

    /**
     * Indicate that the grade is for a specific question.
     */
    public function forQuestion(Question $question): static
    {
        return $this->state(fn (array $attributes) => [
            'question_id' => $question->id,
        ]);
    }
}