<?php

namespace App\Http\Controllers\Api\V1\Mobile\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
class TeacherStudentsController extends Controller
{
    public function index(Teacher $teacher): JsonResponse
    {
        try {
            $teacher = Auth::user()->teacher;
            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current user is not a teacher.',
                ], 403);
            }
            $sections = $teacher->sectionsTaught();
            $sections->load(['students.user', 'classroom']);
            $result = [];

            foreach ($sections as $section) {
                $className = $section->classroom->name;
                $sectionName = $section->name;
                $students = $section->students->map(fn($student) => [
                    'id' => $student->id,
                    'first_name' => $student->user->first_name,
                    'last_name' => $student->user->last_name,
                    'gender' => $student->gender,
                ]);
                $subjects = $section
                    ->sectionSubjects
                    ->where('teacher_id', $teacher->id)
                    ->values()
                    ->map(function ($pivot, $index) {
                        return [
                            'id' => $pivot->subject->id,
                            'name' => $pivot->subject->name,
                        ];
                    });
                $result[$className][$sectionName]['section_id'] = $section->id;
                $result[$className][$sectionName]['subjects'] = $subjects;
                $result[$className][$sectionName]['students'] = $students;
            }
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching teacher students', [
                'teacher_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve students.',
            ], 500);
        }
    }
}
