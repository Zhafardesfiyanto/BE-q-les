<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateClassRequest;
use App\Http\Requests\JoinClassRequest;
use App\Models\Classroom;
use App\Services\ClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClassController extends Controller
{
    /**
     * @var ClassService
     */
    protected $classService;

    /**
     * Constructor.
     *
     * @param ClassService $classService
     */
    public function __construct(ClassService $classService)
    {
        $this->classService = $classService;
    }

    /**
     * Create a new classroom.
     *
     * @param CreateClassRequest $request
     * @return JsonResponse
     */
    public function store(CreateClassRequest $request): JsonResponse
    {
        try {
            $classroom = $this->classService->create($request->user(), $request->validated());

            return response()->json([
                'message' => 'Classroom created successfully.',
                'data' => [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'code' => $classroom->code,
                    'teacher_id' => $classroom->teacher_id,
                    'created_at' => $classroom->created_at,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], $e->status);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create classroom.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List classrooms with search and pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $keyword = $request->query('search');
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 15);

            $paginator = $this->classService->search($request->user(), $keyword, $page, $perPage);

            return response()->json([
                'message' => 'Classrooms retrieved successfully.',
                'data' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve classrooms.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get classroom details with code.
     *
     * @param Classroom $classroom
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Classroom $classroom, Request $request): JsonResponse
    {
        try {
            $details = $this->classService->getDetails($classroom, $request->user());

            return response()->json([
                'message' => 'Classroom details retrieved successfully.',
                'data' => $details,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Access denied.',
                'errors' => $e->errors(),
            ], $e->status);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve classroom details.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get classroom members.
     *
     * @param Classroom $classroom
     * @param Request $request
     * @return JsonResponse
     */
    public function getMembers(Classroom $classroom, Request $request): JsonResponse
    {
        try {
            $members = $this->classService->getMembers($classroom);

            return response()->json([
                'message' => 'Classroom members retrieved successfully.',
                'data' => $members,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve classroom members.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a member from classroom.
     *
     * @param Classroom $classroom
     * @param int $userId
     * @param Request $request
     * @return JsonResponse
     */
    public function removeMember(Classroom $classroom, int $userId, Request $request): JsonResponse
    {
        try {
            $this->classService->removeMember($classroom, $userId);

            return response()->json([
                'message' => 'Member removed from classroom successfully.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Failed to remove member.',
                'errors' => $e->errors(),
            ], $e->status);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove member.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Join a classroom using code.
     *
     * @param JoinClassRequest $request
     * @return JsonResponse
     */
    public function join(JoinClassRequest $request): JsonResponse
    {
        try {
            $classroom = $this->classService->join($request->user(), $request->input('code'));

            return response()->json([
                'message' => 'Successfully joined classroom.',
                'data' => [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'code' => $classroom->code,
                    'teacher_id' => $classroom->teacher_id,
                    'teacher_name' => $classroom->teacher->name,
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Failed to join classroom.',
                'errors' => $e->errors(),
            ], $e->status);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to join classroom.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}