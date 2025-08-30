<?php

namespace App\Http\Controllers\Api\V1\Mobile\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Supervisor\FinalizeExamResultsRequest;
use App\Models\Exam;
use App\Services\Mobile\ExamApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupervisorExamApprovalController extends Controller
{
    public function __construct(private ExamApprovalService $service)
    {
        // Route group has auth + IsSupervisor + active.semester
    }

    /**
     * GET: waiting (not released) exams in supervisor's stage.
     */
    public function waiting(Request $request): JsonResponse
    {
        try {
            $exams = $this->service->waitingExamsForSupervisorStage();
            return response()->json([
                'message' => __('mobile/supervisor/exam_approval.messages.waiting_loaded'),
                'exams' => $exams,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Supervisor waiting exams error', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('mobile/supervisor/exam_approval.messages.server_error')], 500);
        }
    }

    /**
     * GET: attempts for a chosen exam (default: pending).
     * ?status=pending|approved|all
     */
    public function attempts(Exam $exam, Request $request): JsonResponse
    {
        try {
            $status = $request->query('status', 'pending');
            $attempts = $this->service->examAttempts($exam, $status);

            return response()->json([
                'success' => true,
                'message' => __('mobile/supervisor/exam_approval.messages.attempts_loaded'),
                'exam_id' => $exam->id,
                'attempts' => $attempts,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'success' => false], 422);
        } catch (\Throwable $e) {
            Log::error('Supervisor exam attempts error', ['exam_id' => $exam->id ?? null, 'error' => $e->getMessage()]);
            return response()->json(['message' => __('mobile/supervisor/exam_approval.messages.server_error'), 'success' => false], 500);
        }
    }

    /**
     * POST: finalize results (approve + release exam).
     */
    public function finalize(Exam $exam, FinalizeExamResultsRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();
            $attempts = $payload['attempts'];

            $result = $this->service->finalize($exam, $attempts);

            return response()->json([
                'success' => true,
                'message' => __('mobile/supervisor/exam_approval.messages.finalized'),
                'approved' => $result['approved'],
                'exam' => $result['exam'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'success' => false], 422);
        } catch (\Throwable $e) {
            Log::error('Supervisor finalize exam error', ['exam_id' => $exam->id ?? null, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => __('mobile/supervisor/exam_approval.messages.server_error')], 500);
        }
    }
}
