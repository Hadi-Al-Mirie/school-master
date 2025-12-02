<?php
namespace App\Http\Controllers\Api\V1\Mobile\Teacher;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Http\Requests\Mobile\Teacher\ScheduleCallRequest;
use App\Models\ScheduledCall;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Mobile\Teacher\DeleteScheduledCallRequest;
use App\Services\Mobile\CallService;
use Illuminate\Support\Facades\Auth;
class TeacherCallController extends Controller
{
    public function __construct(private CallService $callService)
    {
    }
    public function schedule(ScheduleCallRequest $request)
    {
        $user=Auth::user();
        $data=$request->validated();
        $result=$this->callService->teacherSchedule($data,$user);
        return response()->json($result['body'],$result['status']);
    }
    public function startScheduled(ScheduledCall $scheduled_call)
    {
        $user=Auth::user();
        $result=$this->callService->teacherStartScheduled($scheduled_call,$user);
        return response()->json($result['body'],$result['status']);
    }
    public function scheduledCalls()
    {
        $user=Auth::user();
        $result=$this->callService->teacherScheduledCalls($user);
        return response()->json($result['body'],$result['status']);
    }
    public function end(Call $call)
    {
        $user=Auth::user();
        $result=$this->callService->teacherEnd($call,$user);
        return response()->json($result['body'],$result['status']);
    }
    public function destroyScheduled(DeleteScheduledCallRequest $request,ScheduledCall $scheduled_call):JsonResponse
    {
        $user=Auth::user();
        $request->validated();
        $result=$this->callService->deleteScheduledByTeacher($scheduled_call,$user);
        return response()->json($result['body'],$result['status']);
    }
}
