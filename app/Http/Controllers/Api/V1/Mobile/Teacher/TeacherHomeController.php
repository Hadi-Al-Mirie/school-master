<?php

namespace App\Http\Controllers\Api\V1\Mobile\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Log;
class TeacherHomeController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $teacher = $user->teacher;
        $popularity = $teacher->teacherPopularities()->count();
        $sections = $teacher->sectionsTaught()->map(function ($section) use ($teacher) {
            $byPoints = $section->studentRanking('all', $teacher->id)->first();
            $byNotes = $section
                ->studentRanking('notes', $teacher->id)
                ->first();
            $byExams = $section
                ->studentRanking('exams', $teacher->id)
                ->first();
            $subjectIds = $teacher->sectionSubjects()
                ->where('section_id', $section->id)
                ->pluck('subject_id')
                ->toArray();
            $avgExam = $section->averageExamResultForTeacher($teacher->id, $subjectIds);
            return [
                'id' => $section->id,
                'name' => $section->name,
                'classroom' => $section->classroom->name,
                'top_by_points' => $byPoints,
                'top_by_notes' => $byNotes,
                'top_by_exams' => $byExams,
                'avg_exam_result' => round($avgExam ?? 0, 2),
            ];
        });
        return response()->json([
            'success' => true,
            'teacher' => [
                'first_name' => $teacher->user->first_name,
                'last_name' => $teacher->user->last_name,
                'popularity' => $popularity,
            ],
            'sections' => $sections,
        ]);
    }
}