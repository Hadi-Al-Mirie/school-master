<?php
namespace App\Http\Controllers\Api\V1\Mobile\Teacher;
use App\Http\Controllers\Controller;
use App\Services\Mobile\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
class TeacherScheduleController extends Controller
{
    public function __construct(private ScheduleService $scheduleService)
    {
    }
    public function weekly(): JsonResponse
    {
        $user = Auth::user();
        $result = $this->scheduleService->teacherWeekly($user);
        return response()->json($result['body'], $result['status']);
    }
}
