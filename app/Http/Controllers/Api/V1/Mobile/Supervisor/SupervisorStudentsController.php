<?php

namespace App\Http\Controllers\Api\V1\Mobile\Supervisor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SupervisorStudentsController extends Controller
{
    public function index()
    {
        try {
            $user = Auth::user();
            $supervisor = $user->supervisor()->with('stage')->first();

            if (!$supervisor || !$supervisor->stage) {
                return response()->json(['success' => false, 'message' => 'Supervisor or stage not found'], 422);
            }

            // Load classrooms -> sections -> students.user in one go
            $classrooms = $supervisor->stage->classrooms()
                ->with(['sections.students.user'])
                ->get(['id', 'name']);

            $result = [];
            foreach ($classrooms as $classroom) {
                foreach ($classroom->sections as $section) {
                    $students = $section->students->map(fn($s) => [
                        'id' => $s->id,
                        'first_name' => $s->user->first_name,
                        'last_name' => $s->user->last_name,
                        'gender' => $s->gender,
                    ])->values(); // ensure array indexes

                    $result[$classroom->name][$section->name]['students'] = $students;
                }
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Log::error('Error fetching supervisor students', [
                'supervisor_user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Could not retrieve students.'], 500);
        }
    }
}
