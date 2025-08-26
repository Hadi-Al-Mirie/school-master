<?php

namespace App\Http\Controllers\Api\V1\Mobile\Student;

use App\Http\Controllers\Controller;
use App\Services\Mobile\StudentScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class StudentScheduleController extends Controller
{
    public function __construct(private StudentScheduleService $service)
    {
    }

    // GET /v1/mobile/student/schedule/weekly
    public function weekly(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $res = $this->service->weekly($userId);

            if (!$res['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => $res['message'],
                    'data'    => [],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => __('mobile/student/schedule.success.loaded'),
                'data'    => $res['data'],
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/schedule.errors.unexpected'),
            ], 500);
        }
    }
}
