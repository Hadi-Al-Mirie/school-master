<?php

namespace App\Http\Controllers\Api\V1\Mobile\Supervisor;

use App\Http\Controllers\Controller;
use App\Services\Mobile\ClassroomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SupervisorClassroomController extends Controller
{
    public function __construct(private ClassroomService $classroomService)
    {
    }

    public function classroomsWithSubjects(): JsonResponse
    {
        try {
            $data = $this->classroomService->classroomsWithSubjectsForSupervisorStage();

            return response()->json([
                'message' => __('mobile/supervisor/classrooms.messages.loaded'),
                'classrooms' => $data,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Supervisor classrooms-with-subjects error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => __('mobile/supervisor/classrooms.messages.server_error'),
            ], 500);
        }
    }
}
