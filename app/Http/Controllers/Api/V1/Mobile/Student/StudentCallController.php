<?php
namespace App\Http\Controllers\Api\V1\Mobile\Student;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Student\JoinCallRequest;
use App\Services\Mobile\CallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
class StudentCallController extends Controller
{
    public function __construct(private CallService $callService)
    {
    }
    public function join(JoinCallRequest $request):JsonResponse
    {
        $user=Auth::user();
        $data=$request->validated();
        $result=$this->callService->studentJoin($data,$user);
        return response()->json($result['body'],$result['status']);
    }
    public function scheduledCalls()
    {
        $user=Auth::user();
        $result=$this->callService->studentScheduledCalls($user);
        return response()->json($result['body'],$result['status']);
    }
}
