<?php

namespace App\Services\Mobile;

use App\Models\Dictation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Support\Facades\DB;

class StudentDictationService
{
    public function index(int $userId, array $filters = []): array
    {
        $student = Student::with('user:id,first_name,last_name')
            ->where('user_id', $userId)
            ->first();
        if (!$student) {
            return ['ok' => false, 'message' => __('mobile/student/dictations.errors.student_not_found')];
        }
        $activeYear = Year::where('is_active', true)->first();
        if (!$activeYear) {
            return ['ok' => false, 'message' => __('mobile/student/dictations.errors.active_year_not_found')];
        }
        $semesterIds = Semester::where('year_id', $activeYear->id)->pluck('id');
        $q = Dictation::query()
            ->with([
                'semester:id,name,year_id',
                'section:id,name',
                'teacher.user:id,first_name,last_name,email',
            ])
            ->where('student_id', $student->id)
            ->whereIn('semester_id', $semesterIds);
        if (!empty($filters['semester_id']) && $semesterIds->contains((int) $filters['semester_id'])) {
            $q->where('semester_id', (int) $filters['semester_id']);
        }
        if (!empty($filters['teacher_id'])) {
            $q->where('teacher_id', (int) $filters['teacher_id']);
        }
        if (!empty($filters['section_id'])) {
            $q->where('section_id', (int) $filters['section_id']);
        }
        switch ($filters['sort'] ?? 'newest') {
            case 'oldest':
                $q->orderBy('created_at', 'asc')->orderBy('id', 'asc');
                break;
            case 'highest_result':
                $q->orderByDesc(DB::raw('COALESCE(result,0)'));
                break;
            case 'lowest_result':
                $q->orderBy(DB::raw('COALESCE(result,0)'));
                break;
            default:
                $q->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        }
        $dictations = $q->get();
        $scope = Dictation::query()
            ->where('student_id', $student->id)
            ->whereIn('semester_id', $semesterIds)
            ->when(!empty($filters['semester_id']) && $semesterIds->contains((int) $filters['semester_id']), fn($qq) => $qq->where('semester_id', (int) $filters['semester_id']))
            ->when(!empty($filters['teacher_id']), fn($qq) => $qq->where('teacher_id', (int) $filters['teacher_id']))
            ->when(!empty($filters['section_id']), fn($qq) => $qq->where('section_id', (int) $filters['section_id']));
        $count = (int) (clone $scope)->count();
        $avgResult = (float) (clone $scope)->avg('result');
        $bestRow = (clone $scope)->orderByDesc('result')->first();
        $lastRow = (clone $scope)->orderByDesc('id')->first();
        $items = $dictations->map(function (Dictation $d) {
            return [
                'id' => $d->id,
                'result' => (float) $d->result,
                'semester' => $d->semester ? ['id' => $d->semester->id, 'name' => $d->semester->name] : null,
                'section' => $d->section ? ['id' => $d->section->id, 'name' => $d->section->name] : null,
                'teacher' => $d->teacher && $d->teacher->user ? [
                    'id' => $d->teacher->id,
                    'name' => trim(($d->teacher->user->first_name ?? '') . ' ' . ($d->teacher->user->last_name ?? '')),
                    'email' => $d->teacher->user->email ?? null,
                ] : null,
                'created_at' => $d->created_at,
            ];
        });

        return [
            'ok' => true,
            'message' => __('mobile/student/dictations.success.loaded'),
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->user ? ($student->user->first_name . ' ' . $student->user->last_name) : null,
                ],
                'year' => ['id' => $activeYear->id, 'name' => $activeYear->name],
                'summary' => [
                    'count' => $count,
                    'avg_result' => $avgResult,
                    'best' => $bestRow ? ['id' => $bestRow->id, 'result' => (float) $bestRow->result] : null,
                    'last' => $lastRow ? ['id' => $lastRow->id, 'result' => (float) $lastRow->result, 'created_at' => $lastRow->created_at] : null,
                ],
                'dictations' => $items,
                'count' => $items->count(),
            ],
        ];
    }
}
