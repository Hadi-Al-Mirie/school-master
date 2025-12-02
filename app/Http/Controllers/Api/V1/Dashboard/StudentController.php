<?php
namespace App\Http\Controllers\Api\V1\Dashboard;
use App\Models\Student;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Student\StudentStoreRequest;
use App\Services\Dashboard\StudentService;
class StudentController extends Controller
{
    protected StudentService $studentService;
    public function __construct(StudentService $studentService)
    {
        $this->studentService=$studentService;
    }
    public function store(StudentStoreRequest $request)
    {
        $validated=$request->validated();
        $result=$this->studentService->store($validated);
        return response()->json($result,201);
    }
    public function show(Student $student)
    {
        return $student;
    }
}
