<?php

namespace App\Http\Controllers\Api\V1\Mobile\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Student\StudentExamsIndexRequest;
use App\Services\Mobile\StudentExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class StudentExamController extends Controller
{
    public function __construct(private StudentExamService $service)
    {
    }

    public function index(StudentExamsIndexRequest $request): JsonResponse
    {
        try {
            $userId  = Auth::id();
            $filters = $request->only([
                'semester_id','exam_id','subject_id','teacher_id','status',
                'min_result','max_result','submitted_from','submitted_to','sort'
            ]);

            $result = $this->service->index($userId, $filters);

            if (!$result['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'filters' => $filters,
                    'data'    => [],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => __('mobile/student/exams.success.loaded'),
                'filters' => $filters,
                'data'    => $result['data'],
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/exams.errors.unexpected'),
            ], 500);
        }
    }
}
