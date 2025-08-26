<?php

namespace App\Http\Controllers\Api\V1\Mobile\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Student\StudentAvailableQuizzesRequest;
use App\Http\Requests\Mobile\Student\StudentQuizSubmitRequest;
use App\Models\Quiz;
use App\Services\Mobile\StudentQuizSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class StudentQuizSubmissionController extends Controller
{
    public function __construct(private StudentQuizSubmissionService $service)
    {
    }

    // GET /quizzes/submittable
    public function submittable(StudentAvailableQuizzesRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $filters = $request->only(['subject_id', 'sort']);

            $res = $this->service->submittableQuizzes($userId, $filters);

            if (!$res['ok']) {
                return response()->json(['success' => false, 'message' => $res['message']], 404);
            }

            return response()->json([
                'success' => true,
                'message' => __('mobile/student/quiz_submission.success.submittable_loaded'),
                'filters' => $filters,
                'data' => $res['data'],
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/quiz_submission.errors.unexpected'),
            ], 500);
        }
    }

    // GET /quizzes/{quiz}/questions
    public function questions(Quiz $quiz): JsonResponse
    {
        try {
            $userId = Auth::id();

            $res = $this->service->getQuizForStudent($userId, $quiz);
            if (!$res['ok']) {
                return response()->json(['success' => false, 'message' => $res['message']], $res['code'] ?? 403);
            }

            return response()->json([
                'success' => true,
                'message' => __('mobile/student/quiz_submission.success.quiz_loaded'),
                'data' => $res['data'],
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/quiz_submission.errors.unexpected'),
            ], 500);
        }
    }

    // POST /quizzes/{quiz}/submit
    public function submit(StudentQuizSubmitRequest $request, Quiz $quiz): JsonResponse
    {
        try {
            $userId = Auth::id();
            $answers = $request->input('answers', []);

            $res = $this->service->submit($userId, $quiz, $answers);
            if (!$res['ok']) {
                return response()->json(['success' => false, 'message' => $res['message']], $res['code'] ?? 422);
            }

            return response()->json([
                'success' => true,
                'message' => __('mobile/student/quiz_submission.success.submitted'),
                'data' => $res['data'], // {quiz_id, final_result}
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/quiz_submission.errors.unexpected'),
            ], 500);
        }
    }
}