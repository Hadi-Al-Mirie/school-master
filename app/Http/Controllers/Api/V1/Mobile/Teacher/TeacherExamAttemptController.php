<?php
namespace App\Http\Controllers\Api\V1\Mobile\Teacher;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Mobile\Teacher\SubmitExamResultsRequest;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\Mobile\ExamService;
class TeacherExamAttemptController extends Controller
{
    public function __construct(private ExamService $examService)
    {
    }
    public function enterable(Request $request):JsonResponse
    {
        $user=Auth::user();
        $result=$this->examService->teacherEnterable($user);
        return response()->json($result['body'],$result['status']);
    }
    public function submitResults(SubmitExamResultsRequest $request,Exam $exam):JsonResponse
    {
        $user=Auth::user();
        $payload=$request->validated();
        $result=$this->examService->teacherSubmitResults($payload,$exam,$user);
        return response()->json($result['body'],$result['status']);
    }
}
