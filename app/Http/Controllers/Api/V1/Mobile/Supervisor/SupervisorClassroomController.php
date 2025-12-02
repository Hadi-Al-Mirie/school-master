<?php

namespace App\Http\Controllers\Api\V1\Mobile\Supervisor;

use App\Http\Controllers\Controller;
use App\Services\Mobile\ClassroomService;
use Illuminate\Http\JsonResponse;

class SupervisorClassroomController extends Controller
{
    public function __construct(private ClassroomService $classroomService)
    {
    }

    public function classroomsWithSubjects(): JsonResponse
    {
        $data = $this->classroomService->classroomsWithSubjectsForSupervisorStage();

        return response()->json([
            'message' => __('mobile/supervisor/classrooms.messages.loaded'),
            'classrooms' => $data,
        ]);
    }
}