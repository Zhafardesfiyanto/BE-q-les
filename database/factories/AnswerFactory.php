<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\Question;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Answer>
 */
class AnswerFactory extends Factory
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
            'selected_options' => [0],
            'essay_answer' => null,
        ];
    }

    /**
     * Indicate that the answer is for an essay question.
     */
    public function essay(): static
    {
        return $this->state(fn (array $attributes) => [
            'selected_options' => null,
            'essay_answer' => $this->faker->paragraph(),
        ]);
    }

    /**
     * Indicate that the answer belongs to a specific submission.
     */
    public function forSubmission(Submission $submission): static
    {
        return $this->state(fn (array $attributes) => [
            'submission_id' => $submission->id,
        ]);
    }

    /**
     * Indicate that the answer is for a specific question.
     */
    public function forQuestion(Question $question): static
    {
        return $this->state(fn (array $attributes) => [
            'question_id' => $question->id,
        ]);
    }
}