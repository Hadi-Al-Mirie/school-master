<?php
namespace App\Services\Dashboard;
use App\Models\Student;
use App\Models\User;
use App\Models\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class StudentService
{
    public function store(array $validated)
    {
        return DB::transaction(function () use ($validated) {
            $email = $validated['email'];
            $password = $validated['password'];
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $email,
                'password' => Hash::make($password),
                'role_id' => 1
            ]);
            $section = Section::find($validated['section_id']);
            $classroom = $section->classroom;
            $classroom_id = $classroom->id;
            $stage_id = $classroom->stage->id;
            $student = Student::create([
                'user_id' => $user->id,
                'gender' => $validated['gender'],
                'father_name' => $validated['father_name'],
                'mother_name' => $validated['mother_name'],
                'location' => $validated['location'],
                'section_id' => $validated['section_id'],
                'father_number' => $validated['father_number'],
                'mother_number' => $validated['mother_number'],
                'birth_day' => $validated['birth_day'],
                'classroom_id' => $classroom_id,
                'stage_id' => $stage_id
            ]);
            return [
                'success' => true,
                'message' => 'Student created successfully',
                'data' => [
                    'student' => $student,
                    'user_account' => [
                        'email' => $email,
                        'password' => $password
                    ]
                ]
            ];
        });
    }
}