<?php

namespace App\Http\Controllers\Api\v1\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SupervisorController extends Controller
{
    public function index()
    {
        $supervisor = Supervisor::all();
        return response()->json($supervisor, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:150',
            'last_name'  => 'required|string|max:150',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'required|string|min:10|max:20',
        ]);
         $email = $request->email ;
        $password = $request->password ;

         DB::beginTransaction();
    try {

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $email,
            'password'   =>$password,
            'role_id'    => 4,
        ]);


        $supervisor = Supervisor::create([
            'user_id' => $user->id,
            'phone' => $validated['phone'],
            'salary' => $validated['salary'],   
        ]);


       DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Supervisor created successfully',
            'data' => [
                'supervisor' => $supervisor,
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

    public function show($id)
    {
        $supervisor = Supervisor::findOrFail($id);
        return response()->json($supervisor, 200);
    }
}
