<?php

namespace App\Http\Controllers\Api\V1\Mobile\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Student\StudentAttendancesIndexRequest;
use App\Services\Mobile\StudentAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class StudentAttendanceController extends Controller
{
    public function __construct(private StudentAttendanceService $service)
    {
    }

    public function index(StudentAttendancesIndexRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $filters = $request->only(['attendance_type_id', 'semester_id', 'date_from', 'date_to', 'sort']);

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
                'message' => __('mobile/student/attendance.success.loaded'),
                'filters' => $filters,
                'data' => $result['data'],
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/attendance.errors.unexpected'),
            ], 500);
        }
    }
}