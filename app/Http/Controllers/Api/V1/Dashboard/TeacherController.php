<?php
namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\teacher\StoreTeacherRequest;
use App\Models\Teacher;
use App\Services\Dashboard\TeacherService;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    protected TeacherService $teacherService;
    public function __construct(TeacherService $teacherService)
    {
        $this->teacherService = $teacherService;
    }
    public function index()
    {
        $teachers = $this->teacherService->index();
        return response()->json($teachers, 200);
    }
    public function store(StoreTeacherRequest $request)
    {
        $validated = $request->validated();
        $result = $this->teacherService->store($validated, $request->input('section_subjects', []));
        return response()->json($result, 201);
    }
    public function show($id)
    {
        $teacher = $this->teacherService->show($id);
        return response()->json($teacher, 200);
    }
    public function update(Request $request, $id)
    {
        $teacher = $this->teacherService->update($request->all(), $id);
        return response()->json($teacher, 200);
    }
    public function destroy(Teacher $teacher)
    {
        $this->teacherService->destroy($teacher);
        return response()->json(['message' => 'Teacher deleted'], 200);
    }
}
