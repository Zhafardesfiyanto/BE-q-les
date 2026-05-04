<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Answer;
use App\Models\Assignment;
use App\Models\ClassMember;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    /**
     * Create a new assignment with questions.
     *
     * @param User $guru
     * @param Classroom $classroom
     * @param array $data
     * @return Assignment
     * @throws ValidationException
     */
    public function create(User $guru, Classroom $classroom, array $data): Assignment
    {
        // Validate that user is a Guru
        if ($guru->role !== UserRole::Guru) {
            throw ValidationException::withMessages([
                'role' => ['Only Guru can create assignments.'],
            ])->status(403);
        }

        // Validate that the Guru owns this classroom
        if ($classroom->teacher_id !== $guru->id) {
            throw ValidationException::withMessages([
                'classroom' => ['You do not own this classroom.'],
            ])->status(403);
        }

        // Validate questions array is not empty
        $questions = $data['questions'] ?? [];
        if (empty($questions)) {
            throw ValidationException::withMessages([
                'questions' => ['Daftar soal tidak boleh kosong.'],
            ])->status(422);
        }

        // Validate each question
        foreach ($questions as $index => $question) {
            $this->validateQuestion($question, $index);
        }

        return DB::transaction(function () use ($classroom, $data, $questions) {
            // Create the assignment
            $assignment = Assignment::create([
                'classroom_id' => $classroom->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'exam_mode' => $data['exam_mode'] ?? false,
                'due_at' => $data['due_at'] ?? null,
            ]);

            // Create questions
            foreach ($questions as $index => $questionData) {
                Question::create([
                    'assignment_id' => $assignment->id,
                    'body' => $questionData['body'],
                    'type' => $questionData['type'],
                    'options' => $questionData['options'] ?? null,
                    'weight' => $questionData['weight'],
                    'order' => $questionData['order'] ?? ($index + 1),
                ]);
            }

            return $assignment->load('questions');
        });
    }

    /**
     * Validate a single question based on its type.
     *
     * @param array $question
     * @param int $index
     * @throws ValidationException
     */
    protected function validateQuestion(array $question, int $index): void
    {
        $type = $question['type'] ?? null;

        if ($type === QuestionType::PilihanGanda->value || $type === QuestionType::PilihanGanda) {
            $options = $question['options'] ?? [];
            $correctCount = count(array_filter($options, fn($opt) => ($opt['is_correct'] ?? false) === true));

            if ($correctCount !== 1) {
                throw ValidationException::withMessages([
                    "questions.{$index}.options" => ['Soal pilihan_ganda harus memiliki tepat satu jawaban benar.'],
                ])->status(422);
            }
        } elseif ($type === QuestionType::PilihanGandaKompleks->value || $type === QuestionType::PilihanGandaKompleks) {
            $options = $question['options'] ?? [];
            $correctCount = count(array_filter($options, fn($opt) => ($opt['is_correct'] ?? false) === true));

            if ($correctCount < 1) {
                throw ValidationException::withMessages([
                    "questions.{$index}.options" => ['Soal pilihan_ganda_kompleks harus memiliki minimal satu jawaban benar.'],
                ])->status(422);
            }
        }
        // uraian: no options validation needed
    }

    /**
     * Get all submissions for an assignment (Guru only).
     *
     * @param Assignment $assignment
     * @param User $guru
     * @return Collection
     * @throws ValidationException
     */
    public function getSubmissions(Assignment $assignment, User $guru): Collection
    {
        // Validate that user is a Guru
        if ($guru->role !== UserRole::Guru) {
            throw ValidationException::withMessages([
                'role' => ['Only Guru can view all submissions.'],
            ])->status(403);
        }

        // Validate that the Guru owns the classroom of this assignment
        if ($assignment->classroom->teacher_id !== $guru->id) {
            throw ValidationException::withMessages([
                'assignment' => ['You do not own this assignment.'],
            ])->status(403);
        }

        return $assignment->submissions()->with(['user', 'answers'])->get();
    }

    /**
     * Submit an assignment (Murid only).
     *
     * @param User $murid
     * @param Assignment $assignment
     * @param array $data
     * @return Submission
     * @throws ValidationException
     */
    public function submitAssignment(User $murid, Assignment $assignment, array $data): Submission
    {
        // Validate that user is a Murid
        if ($murid->role !== UserRole::Murid) {
            throw ValidationException::withMessages([
                'role' => ['Only Murid can submit assignments.'],
            ])->status(403);
        }

        // Validate that the Murid is a member of the classroom
        $isMember = ClassMember::where('classroom_id', $assignment->classroom_id)
            ->where('user_id', $murid->id)
            ->exists();

        if (!$isMember) {
            throw ValidationException::withMessages([
                'membership' => ['Anda bukan anggota kelas ini.'],
            ])->status(403);
        }

        // Check for duplicate submission
        $existingSubmission = Submission::where('assignment_id', $assignment->id)
            ->where('user_id', $murid->id)
            ->exists();

        if ($existingSubmission) {
            throw ValidationException::withMessages([
                'submission' => ['Anda sudah mengumpulkan tugas ini.'],
            ])->status(409);
        }

        // Validate exam mode: gesture_log required
        if ($assignment->exam_mode && empty($data['gesture_log'])) {
            throw ValidationException::withMessages([
                'gesture_log' => ['Rekap gestur wajib disertakan pada mode ujian.'],
            ])->status(422);
        }

        // Determine submission status (late or on time)
        $submittedAt = now();
        $status = SubmissionStatus::Dikumpulkan;

        if ($assignment->due_at && $submittedAt->isAfter($assignment->due_at)) {
            $status = SubmissionStatus::Terlambat;
        }

        return DB::transaction(function () use ($murid, $assignment, $data, $status, $submittedAt) {
            // Create submission
            $submission = Submission::create([
                'assignment_id' => $assignment->id,
                'user_id' => $murid->id,
                'status' => $status,
                'screenshot_path' => $data['screenshot_path'] ?? null,
                'gesture_log' => $data['gesture_log'] ?? null,
                'submitted_at' => $submittedAt,
            ]);

            // Save answers
            $answers = $data['answers'] ?? [];
            foreach ($answers as $answerData) {
                Answer::create([
                    'submission_id' => $submission->id,
                    'question_id' => $answerData['question_id'],
                    'selected_options' => $answerData['selected_options'] ?? null,
                    'essay_answer' => $answerData['essay_answer'] ?? null,
                ]);
            }

            return $submission->load('answers');
        });
    }

    /**
     * List assignments in a classroom.
     *
     * @param Classroom $classroom
     * @param User $user
     * @return Collection
     * @throws ValidationException
     */
    public function listByClassroom(Classroom $classroom, User $user): Collection
    {
        // Check access: Guru must own the classroom, Murid must be a member
        if ($user->role === UserRole::Guru) {
            if ($classroom->teacher_id !== $user->id) {
                throw ValidationException::withMessages([
                    'classroom' => ['You do not have access to this classroom.'],
                ])->status(403);
            }
        } elseif ($user->role === UserRole::Murid) {
            $isMember = ClassMember::where('classroom_id', $classroom->id)
                ->where('user_id', $user->id)
                ->exists();

            if (!$isMember) {
                throw ValidationException::withMessages([
                    'classroom' => ['You are not a member of this classroom.'],
                ])->status(403);
            }
        }

        return $classroom->assignments()->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get assignment detail with questions.
     *
     * @param Assignment $assignment
     * @param User $user
     * @return Assignment
     * @throws ValidationException
     */
    public function getDetail(Assignment $assignment, User $user): Assignment
    {
        $classroom = $assignment->classroom;

        // Check access
        if ($user->role === UserRole::Guru) {
            if ($classroom->teacher_id !== $user->id) {
                throw ValidationException::withMessages([
                    'assignment' => ['You do not have access to this assignment.'],
                ])->status(403);
            }
        } elseif ($user->role === UserRole::Murid) {
            $isMember = ClassMember::where('classroom_id', $classroom->id)
                ->where('user_id', $user->id)
                ->exists();

            if (!$isMember) {
                throw ValidationException::withMessages([
                    'assignment' => ['You are not a member of this classroom.'],
                ])->status(403);
            }
        }

        return $assignment->load('questions');
    }
}
