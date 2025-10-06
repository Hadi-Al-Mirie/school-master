<?php

namespace App\Http\Controllers\Api\V1\Mobile\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Supervisor\StoreExamRequest;
use App\Services\Mobile\ExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SupervisorExamController extends Controller
{
    public function __construct(private ExamService $examService)
    {
    }


    public function store(StoreExamRequest $request): JsonResponse
    {
        try {
            $result = $this->examService->createForClassroomSections(
                classroomId: (int) $request->input('classroom_id'),
                subjectId: (int) $request->input('subject_id'),
                maxResult: (int) $request->input('max_result'),
                name: $request->input('name')
            );

            return response()->json([
                'message' => __('mobile/supervisor/exam.messages.created'),
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'exams' => $result['exams'],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Supervisor exam create error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => __('mobile/supervisor/exam.messages.server_error'),
            ], 500);
        }
    }
}