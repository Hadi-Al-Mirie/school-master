<?php

namespace App\Services\Mobile;

use App\Models\Exam;
use App\Models\Semester;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\Supervisor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;

class ExamService
{
    public function activeSemester(): ?Semester
    {
        return Semester::where('is_active', true)->first();
    }

    public function createForClassroomSections(int $classroomId, int $subjectId, float $maxResult, ?string $name = null): array
    {
        $semester = $this->activeSemester();
        if (!$semester) {
            throw new \RuntimeException(__('mobile/supervisor/exam.errors.no_active_semester'));
        }
        $userId = Auth::id();
        $supervisor = Supervisor::where('user_id', $userId)->first();
        if (!$supervisor) {
            throw new \RuntimeException(__('mobile/supervisor/exam.errors.supervisor_not_found'));
        }
        $classroom = Classroom::with('sections:id,classroom_id')->findOrFail($classroomId);
        $subject = Subject::findOrFail($subjectId);
        $sections = $classroom->sections;
        if ($sections->isEmpty()) {
            throw new \RuntimeException(__('mobile/supervisor/exam.errors.no_sections_in_classroom'));
        }
        $baseName = $name ?: ($subject->name . ' - ' . now()->format('Y-m-d H:i'));
        $created = 0;
        $skipped = 0;
        $out = [];
        foreach ($sections as $section) {
            $exam = Exam::create(
                [
                    'section_id' => $section->id,
                    'subject_id' => $subject->id,
                    'name' => $baseName,
                    'created_by' => $supervisor->id,
                    'semester_id' => $semester->id,
                    'status' => 'wait',
                    'max_result' => $maxResult,
                ]
            );
            if ($exam->wasRecentlyCreated) {
                $created++;
            } else {
                $skipped++;
            }
            $out[] = [
                'id' => $exam->id,
                'section_id' => $exam->section_id,
                'subject_id' => $exam->subject_id,
                'semester_id' => $exam->semester_id,
                'name' => $exam->name,
                'status' => $exam->status,
                'max_result' => $exam->max_result,
            ];
        }
        return ['created' => $created, 'skipped' => $skipped, 'exams' => $out];
    }
}