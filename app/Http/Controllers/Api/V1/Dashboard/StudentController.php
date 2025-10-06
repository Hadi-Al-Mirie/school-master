<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Models\Student;
use App\Models\User;
use App\Models\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
class StudentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'first_name' => 'required|string|max:50|min:2',
            'last_name' => 'required|string|max:50|min:2',
            'father_name' => 'required|string|max:255',
            'mother_name' => 'required|string|max:255',
            'gender' => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'birth_day' => 'required|date',
            'location' => 'required|string|min:4',
            'father_number' => 'required|string|max:255',
            'mother_number' => 'required|string|max:255',
            'section_id' => 'required|integer|exists:sections,id',

        ]);
        $email = $request->email;
        $password = $request->password;

        DB::beginTransaction();
        try {

            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $email,
                'password' => Hash::make($password),
                'role_id' => 1,
            ]);
            $section = Section::find($validated['section_id']);
            $classroom = $section->classroom;
            $classroom_id = $classroom->id;
            $stage_id = $classroom->stage->id;

            $student = Student::create([
                'user_id' => $user->id,
                'gender' => $request->gender,
                'father_name' => $validated['father_name'],
                'mother_name' => $validated['mother_name'],
                'location' => $validated['location'],
                'section_id' => $validated['section_id'],
                'father_number' => $validated['father_number'],
                'mother_number' => $validated['mother_number'],
                'birth_day' => $validated['birth_day'],
                'classroom_id' => $classroom_id,
                'stage_id' => $stage_id,
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
            \Log::error('Create student failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create student',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function show(Student $student)
    {
        return $student;
    }
}
