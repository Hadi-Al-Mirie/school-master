<?php
namespace App\Http\Controllers\Api\V1\Mobile\Supervisor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Supervisor\CreateNoteRequest;
use App\Services\Mobile\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
class SupervisorNoteController extends Controller
{
    public function __construct(private NoteService $noteService)
    {
    }
    public function store(CreateNoteRequest $request):JsonResponse
    {
        $user=Auth::user();
        $data=$request->validated();
        $result=$this->noteService->supervisorStore($data,$user);
        return response()->json($result['body'],$result['status']);
    }
}
