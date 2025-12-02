<?php

namespace App\Http\Controllers\Api\V1\Mobile\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Student\StudentDictationsIndexRequest;
use App\Services\Mobile\DictationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StudentDictationController extends Controller
{
    public function __construct(private DictationService $service)
    {
    }

    public function index(StudentDictationsIndexRequest $request): JsonResponse
    {
        $userId = Auth::id();
        $filters = $request->only(['semester_id', 'teacher_id', 'section_id', 'sort']);

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
            'message' => __('mobile/student/dictations.success.loaded'),
            'filters' => $filters,
            'data' => $result['data'],
        ]);
    }
}