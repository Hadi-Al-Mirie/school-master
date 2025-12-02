<?php
namespace App\Http\Controllers\Api\V1\Mobile\Teacher;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Teacher\CreateQuizRequest;
use App\Services\Mobile\QuizService;
class TeacherQuizController extends Controller
{
    public function __construct(private QuizService $quizService)
    {
    }
    public function store(CreateQuizRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        $result = $this->quizService->teacherStoreQuiz($data, $user);
        return response()->json($result['body'], $result['status']);
    }
}