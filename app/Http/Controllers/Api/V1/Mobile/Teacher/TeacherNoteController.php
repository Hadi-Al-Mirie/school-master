<?php
namespace App\Http\Controllers\Api\V1\Mobile\Teacher;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Teacher\CreateNoteRequest;
use App\Services\Mobile\NoteService;
use Illuminate\Support\Facades\Auth;
class TeacherNoteController extends Controller
{
    public function __construct(private NoteService $noteService)
    {
    }
    public function store(CreateNoteRequest $request)
    {
        $user=Auth::user();
        $data=$request->validated();
        $result=$this->noteService->teacherStore($data,$user);
        return response()->json($result['body'],$result['status']);
    }
}
