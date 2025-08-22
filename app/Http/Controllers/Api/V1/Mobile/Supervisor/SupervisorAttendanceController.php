<?php

namespace App\Http\Controllers\Api\V1\Mobile\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Supervisor\StoreAttendanceRequest;
use App\Services\Mobile\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SupervisorAttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendanceService)
    {
        // middleware IsSupervisor already on the route group
    }

    /**
     * POST: register attendance for a student or teacher.
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        try {
            $attendance = $this->attendanceService->createBySupervisor([
                'type' => 'student',
                'attendable_id' => (int) $request->input('attendable_id'),
                'attendance_type_id' => (int) $request->input('attendance_type_id'),
                'att_date' => $request->input('att_date'),
                'justification' => $request->input('justification'),
            ]);

            return response()->json([
                'message' => __('mobile/supervisor/attendance.messages.created'),
                'attendance' => [
                    'id' => $attendance->id,
                    'attendable_type' => $attendance->attendable_type,
                    'attendable_id' => $attendance->attendable_id,
                    'attendance_type_id' => $attendance->attendance_type_id,
                    'by_id' => $attendance->by_id,
                    'semester_id' => $attendance->semester_id,
                    'att_date' => $attendance->att_date,
                    'justification' => $attendance->justification,
                    'created_at' => $attendance->created_at,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            // Known business error (e.g., no active semester)
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Supervisor attendance store error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => __('mobile/supervisor/attendance.messages.server_error'),
            ], 500);
        }
    }
}
