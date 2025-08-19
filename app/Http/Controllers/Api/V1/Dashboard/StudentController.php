<?php

namespace App\Http\Controllers\Api\V1\Dashboard;


use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
class StudentController extends Controller
{
    private function generateStudentId()
    {
        return 'STU-' . strtoupper(Str::random(8));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:50|min:2',
            'last_name' => 'required|string|max:50|min:2',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'stage_id' => 'required|integer|exists:stages,id',
            'classroom_id' => 'required|integer|exists:classrooms,id',
            'section_id' => 'required|integer|exists:sections,id',
            'gender' => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'father_name' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'father_work' => 'nullable|string|max:255',
             'mother_work' => 'nullable|string|max:255',
            'father_number' => 'nullable|string|max:255',
            'mother_number' => 'nullable|string|max:255',
            'birth_day' => 'required|date',
            'location' => 'required|string|min:8',     

        ]); 
         $email = $request->email ;
        $password = $request->password ;

         DB::beginTransaction();
    try {

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $email,
            'password'   => Hash::make($password),
            'role_id'    => 1,
        ]);


        $student = Student::create([
            'user_id' => $user->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'gender' => $request->gender,
            'father_name' => $validated['father_name'],
            'mother_name' =>$validated['mother_name'],
            'location' =>  $validated['location'],
            'stage_id' => $validated['stage_id'],
            'section_id' => $validated['section_id'],
            'classroom_id' => $validated['classroom_id'],
            'father_work' => $validated['father_work'],
             'mother_work' => $validated['mother_work'],
            'father_phone' => $validated['father_number'],
            'mother_phone' => $validated['mother_number'],
            'birth_day' => $validated['birth_day'],   
        ]);


       DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Student created successfully',
            'data' => [
                'student' => $student,
                'user_account' => [
                    'email' => $email,
                    'password' => $password
                ]
            ]
        ], 201);

    } catch (\Exception $e) {

        DB::rollBack();

        \Log::error('Create student failed: '.$e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to create student',
            'error' => $e->getMessage(),
        ], 500);
    }
}
    public function show(Student $student) {
        return $student;
    }        
}
