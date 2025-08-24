<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeacherController extends Controller
{
    public function index()
    {

        $teachers = Teacher::all();
        return response()->json($teachers, 200);

    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|min:5|max:255',
            'password' => 'required|min:8|max:255',
            'phone' => 'required|string|min:10|max:20'
        ]);

        $email = $request->email;
        $password = $request->password;

        DB::beginTransaction();
        try {

            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $email,
                'password' => $password,
                'role_id' => 2,
            ]);


            $teacher = Teacher::create([
                'user_id' => $user->id,
                'phone' => $validated['phone'],
            ]);


            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Teacher created successfully',
                'data' => [
                    'student' => $teacher,
                    'user_account' => [
                        'email' => $email,
                        'password' => $password
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Create teacher failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create teacher',
                'error' => $e->getMessage(),
            ], 500);
        }

    }
    public function show($id)
    {
        $teacher = Teacher::findOrFail($id);
        return response()->json($teacher, 200);
    }

    public function update(Request $request, $id)
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->update($request->all());
        return response()->json($teacher, 200);
    }

    public function destroy(Teacher $teacher)
    {
        $teacher->delete();
        return response()->json(['message' => 'Teacher deleted'], 200);
    }

}
