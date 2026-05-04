<?php

namespace Tests\Unit;

use App\Enums\QuestionType;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Answer;
use App\Models\Assignment;
use App\Models\ClassMember;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\Message;
use App\Models\Question;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_model_has_correct_casts_and_relationships()
    {
        $user = User::factory()->create([
            'role' => UserRole::Murid,
        ]);

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertEquals(UserRole::Murid, $user->role);
        
        // Check relationships exist
        $this->assertTrue(method_exists($user, 'classrooms'));
        $this->assertTrue(method_exists($user, 'enrolledClassrooms'));
        $this->assertTrue(method_exists($user, 'classMembers'));
        $this->assertTrue(method_exists($user, 'submissions'));
        $this->assertTrue(method_exists($user, 'messages'));
    }

    /** @test */
    public function classroom_model_has_correct_relationships()
    {
        $classroom = Classroom::factory()->create();

        $this->assertTrue(method_exists($classroom, 'teacher'));
        $this->assertTrue(method_exists($classroom, 'members'));
        $this->assertTrue(method_exists($classroom, 'classMembers'));
        $this->assertTrue(method_exists($classroom, 'assignments'));
        $this->assertTrue(method_exists($classroom, 'messages'));
    }

    /** @test */
    public function assignment_model_has_correct_casts_and_relationships()
    {
        $assignment = Assignment::factory()->create([
            'exam_mode' => true,
        ]);

        $this->assertIsBool($assignment->exam_mode);
        $this->assertTrue($assignment->exam_mode);
        
        $this->assertTrue(method_exists($assignment, 'classroom'));
        $this->assertTrue(method_exists($assignment, 'questions'));
        $this->assertTrue(method_exists($assignment, 'submissions'));
        $this->assertTrue(method_exists($assignment, 'messages'));
    }

    /** @test */
    public function question_model_has_correct_casts_and_relationships()
    {
        $question = Question::factory()->create([
            'type' => QuestionType::PilihanGanda,
            'options' => [
                ['text' => 'Option A', 'is_correct' => true],
                ['text' => 'Option B', 'is_correct' => false],
            ],
            'weight' => 5.0,
        ]);

        $this->assertInstanceOf(QuestionType::class, $question->type);
        $this->assertEquals(QuestionType::PilihanGanda, $question->type);
        $this->assertIsArray($question->options);
        $this->assertIsString($question->weight); // Decimal cast returns string
        
        $this->assertTrue(method_exists($question, 'assignment'));
        $this->assertTrue(method_exists($question, 'answers'));
        $this->assertTrue(method_exists($question, 'grades'));
    }

    /** @test */
    public function submission_model_has_correct_casts_and_relationships()
    {
        $submission = Submission::factory()->create([
            'status' => SubmissionStatus::Dikumpulkan,
            'gesture_log' => ['gesture1', 'gesture2'],
            'total_grade' => 85.5,
        ]);

        $this->assertInstanceOf(SubmissionStatus::class, $submission->status);
        $this->assertEquals(SubmissionStatus::Dikumpulkan, $submission->status);
        $this->assertIsArray($submission->gesture_log);
        $this->assertIsString($submission->total_grade); // Decimal cast returns string
        
        $this->assertTrue(method_exists($submission, 'assignment'));
        $this->assertTrue(method_exists($submission, 'user'));
        $this->assertTrue(method_exists($submission, 'answers'));
        $this->assertTrue(method_exists($submission, 'grades'));
    }

    /** @test */
    public function class_member_model_has_correct_casts()
    {
        $classMember = ClassMember::factory()->create();

        $this->assertNotNull($classMember->joined_at);
        $this->assertTrue(method_exists($classMember, 'classroom'));
        $this->assertTrue(method_exists($classMember, 'user'));
    }

    /** @test */
    public function answer_model_has_correct_casts_and_relationships()
    {
        $answer = Answer::factory()->create([
            'selected_options' => [1, 2],
        ]);

        $this->assertIsArray($answer->selected_options);
        
        $this->assertTrue(method_exists($answer, 'submission'));
        $this->assertTrue(method_exists($answer, 'question'));
    }

    /** @test */
    public function grade_model_has_correct_casts_and_relationships()
    {
        $grade = Grade::factory()->create([
            'score' => 8.5,
        ]);

        // Cast to decimal returns string in Eloquent
        $this->assertIsString($grade->score);
        $this->assertEquals('8.50', $grade->score);
        
        $this->assertTrue(method_exists($grade, 'submission'));
        $this->assertTrue(method_exists($grade, 'question'));
    }

    /** @test */
    public function message_model_has_correct_relationships()
    {
        $message = Message::factory()->create();

        $this->assertTrue(method_exists($message, 'user'));
        $this->assertTrue(method_exists($message, 'classroom'));
        $this->assertTrue(method_exists($message, 'assignment'));
    }
}