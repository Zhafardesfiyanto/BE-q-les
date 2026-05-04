<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    /**
     * @var AssignmentService
     */
    protected $assignmentService;

    /**
     * Constructor.
     *
     * @param AssignmentService $assignmentService
     */
    public function __construct(AssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Create a new assignment in a classroom.
     * POST /api/classrooms/{classroom}/assignments
     *
     * @param Request $request
     * @param Classroom $classroom
     * @return JsonResponse
     */
    public function store(Request $request, Classroom $classroom): JsonResponse
    {
        try {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'exam_mode' => ['nullable', 'boolean'],
                'due_at' => ['nullable', 'date'],
                'questions' => ['required', 'array'],
                'questions.*.body' => ['required', 'string'],
                'questions.*.type' => ['required', 'string', 'in:pilihan_ganda,pilihan_ganda_kompleks,uraian'],
                'questions.*.options' => ['nullable', 'array'],
                'questions.*.options.*.text' => ['required_with:questions.*.options', 'string'],
                'questions.*.options.*.is_correct' => ['required_with:questions.*.options', 'boolean'],
                'questions.*.weight' => ['required', 'numeric', 'min:0'],
                'questions.*.order' => ['nullable', 'integer', 'min:1'],
            ]);

            $assignment = $this->assignmentService->create($request->user(), $classroom, $data);

            return response()->json([
                'message' => 'Assignment created successfully.',
                'data' => [
                    'id' => $assignment->id,
                    'classroom_id' => $assignment->classroom_id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'exam_mode' => $assignment->exam_mode,
                    'due_at' => $assignment->due_at,
                    'questions' => $assignment->questions->map(fn($q) => [
                        'id' => $q->id,
                        'body' => $q->body,
                        'type' => $q->type,
                        'options' => $q->options,
                        'weight' => $q->weight,
                        'order' => $q->order,
                    ]),
                    'created_at' => $assignment->created_at,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], $e->status);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create assignment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List assignments in a classroom.
     * GET /api/classrooms/{classroom}/assignments
     *
     * @param Request $request
     * @param Classroom $classroom
     * @return JsonResponse
     */
    public function index(Request $request, Classroom $classroom): JsonResponse
    {
        try {
            $assignments = $this->assignmentService->listByClassroom($classroom, $request->user());

            return response()->json([
                'message' => 'Assignments retrieved successfully.',
                'data' => $assignments->map(fn($a) => [
                    'id' => $a->id,
                    'classroom_id' => $a->classroom_id,
                    'title' => $a->title,
                    'description' => $a->description,
                    'exam_mode' => $a->exam_mode,
                    'due_at' => $a->due_at,
                    'created_at' => $a->created_at,
                ]),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Access denied.',
                'errors' => $e->errors(),
            ], $e->status);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve assignments.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get assignment detail with questions.
     * GET /api/assignments/{assignment}
     *
     * @param Request $request
     * @param Assignment $assignment
     * @return JsonResponse
     */
    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        try {
            $assignment = $this->assignmentService->getDetail($assignment, $request->user());

            return response()->json([
                'message' => 'Assignment retrieved successfully.',
                'data' => [
                    'id' => $assignment->id,
                    'classroom_id' => $assignment->classroom_id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'exam_mode' => $assignment->exam_mode,
                    'due_at' => $assignment->due_at,
                    'questions' => $assignment->questions->map(fn($q) => [
                        'id' => $q->id,
                        'body' => $q->body,
                        'type' => $q->type,
                        'options' => $q->options,
                        'weight' => $q->weight,
                        'order' => $q->order,
                    ]),
                    'created_at' => $assignment->created_at,
                    'updated_at' => $assignment->updated_at,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Access denied.',
                'errors' => $e->errors(),
            ], $e->status);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve assignment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit an assignment (Murid).
     * POST /api/assignments/{assignment}/submit
     *
     * @param Request $request
     * @param Assignment $assignment
     * @return JsonResponse
     */
    public function submit(Request $request, Assignment $assignment): JsonResponse
    {
        try {
            $data = $request->validate([
                'screenshot_path' => ['nullable', 'string'],
                'gesture_log' => ['nullable', 'array'],
                'answers' => ['nullable', 'array'],
                'answers.*.question_id' => ['required', 'integer', 'exists:questions,id'],
                'answers.*.selected_options' => ['nullable', 'array'],
                'answers.*.essay_answer' => ['nullable', 'string'],
            ]);

            $submission = $this->assignmentService->submitAssignment($request->user(), $assignment, $data);

            return response()->json([
                'message' => 'Assignment submitted successfully.',
                'data' => [
                    'id' => $submission->id,
                    'assignment_id' => $submission->assignment_id,
                    'user_id' => $submission->user_id,
                    'status' => $submission->status,
                    'submitted_at' => $submission->submitted_at,
                    'answers' => $submission->answers->map(fn($a) => [
                        'id' => $a->id,
                        'question_id' => $a->question_id,
                        'selected_options' => $a->selected_options,
                        'essay_answer' => $a->essay_answer,
                    ]),
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], $e->status);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to submit assignment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all submissions for an assignment (Guru).
     * GET /api/assignments/{assignment}/submissions
     *
     * @param Request $request
     * @param Assignment $assignment
     * @return JsonResponse
     */
    public function submissions(Request $request, Assignment $assignment): JsonResponse
    {
        try {
            $submissions = $this->assignmentService->getSubmissions($assignment, $request->user());

            return response()->json([
                'message' => 'Submissions retrieved successfully.',
                'data' => $submissions->map(fn($s) => [
                    'id' => $s->id,
                    'assignment_id' => $s->assignment_id,
                    'user_id' => $s->user_id,
                    'user_name' => $s->user->name ?? null,
                    'status' => $s->status,
                    'screenshot_path' => $s->screenshot_path,
                    'gesture_log' => $s->gesture_log,
                    'total_grade' => $s->total_grade,
                    'submitted_at' => $s->submitted_at,
                    'answers' => $s->answers->map(fn($a) => [
                        'id' => $a->id,
                        'question_id' => $a->question_id,
                        'selected_options' => $a->selected_options,
                        'essay_answer' => $a->essay_answer,
                    ]),
                ]),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Access denied.',
                'errors' => $e->errors(),
            ], $e->status);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve submissions.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
