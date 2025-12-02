<?php
namespace App\Http\Controllers\Api\V1\Mobile\Teacher;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Teacher\StoreDictationRequest;
use App\Services\Mobile\DictationService;
use Illuminate\Http\JsonResponse;
class TeacherDictationController extends Controller
{
    public function __construct(private DictationService $dictationService)
    {
    }
    public function store(StoreDictationRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $result = $this->dictationService->teacherStore($data, $user);
        return response()->json($result['body'], $result['status']);
    }
}