<?php

namespace App\Http\Controllers\Api\V1\Mobile\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Student\StudentHomeRequest;
use App\Services\Mobile\StudentHomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StudentHomeController extends Controller
{
    public function __construct(private StudentHomeService $service)
    {
    }

    public function index(StudentHomeRequest $request): JsonResponse
    {
        $userId = Auth::id();
        $semesterId = $request->integer('semester_id');

        $result = $this->service->summary($userId, $semesterId);

        if (!$result['ok']) {
            return response()->json([
                'message' => $result['message'],
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => trans('mobile/student/home.success.loaded'),
            'data' => $result['data'],
        ]);
    }
}