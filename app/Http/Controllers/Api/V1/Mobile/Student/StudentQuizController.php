<?php

namespace App\Http\Controllers\Api\V1\Mobile\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Student\StudentQuizzesIndexRequest;
use App\Services\Mobile\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class StudentQuizController extends Controller
{
    public function __construct(private QuizService $service)
    {
    }

    public function index(StudentQuizzesIndexRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $filters = $request->only([
                'semester_id',
                'quiz_id',
                'subject_id',
                'teacher_id',
                'min_score',
                'max_score',
                'submitted_from',
                'submitted_to',
                'sort'
            ]);

            $result = $this->service->index($userId, $filters);

            if (!$result['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'filters' => $filters,
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => __('mobile/student/quizzes.success.loaded'),
                'filters' => $filters,
                'data' => $result['data'],
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/quizzes.errors.unexpected'),
            ], 500);
        }
    }
}