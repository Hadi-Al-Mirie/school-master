<?php

namespace App\Services\Mobile;

use App\Models\ExamAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Support\Facades\DB;

class StudentExamService
{
    /**
     * List student's exam attempts for the current active year with aggregates (no pagination).
     * Note: exam_attempts has no 'submitted_at' column. We use created_at as the submission timestamp.
     */
    public function index(int $userId, array $filters = []): array
    {
        // Student
        $student = Student::with('user:id,first_name,last_name')
            ->where('user_id', $userId)
            ->first();

        if (!$student) {
            return ['ok' => false, 'message' => __('mobile/student/exams.errors.student_not_found')];
        }

        // Active year
        $activeYear = Year::where('is_active', true)->first();
        if (!$activeYear) {
            return ['ok' => false, 'message' => __('mobile/student/exams.errors.active_year_not_found')];
        }

        $semesterIds = Semester::where('year_id', $activeYear->id)->pluck('id');

        // Base query (join exams to scope to active-year semesters)
        $q = ExamAttempt::query()
            ->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
            ->where('exam_attempts.student_id', $student->id)
            ->whereIn('exams.semester_id', $semesterIds)
            ->when(
                !empty($filters['semester_id']) && $semesterIds->contains((int) $filters['semester_id']),
                fn($qq) => $qq->where('exams.semester_id', (int) $filters['semester_id'])
            )
            ->when(
                !empty($filters['exam_id']),
                fn($qq) => $qq->where('exam_attempts.exam_id', (int) $filters['exam_id'])
            )
            ->when(
                !empty($filters['subject_id']),
                fn($qq) => $qq->where('exams.subject_id', (int) $filters['subject_id'])
            )
            // ✅ teacher filter should use exam_attempts.teacher_id
            ->when(
                !empty($filters['teacher_id']),
                fn($qq) => $qq->where('exam_attempts.teacher_id', (int) $filters['teacher_id'])
            )
            ->when(
                !empty($filters['status']),
                fn($qq) => $qq->where('exam_attempts.status', $filters['status'])
            )
            ->when(
                isset($filters['min_result']),
                fn($qq) => $qq->where('exam_attempts.result', '>=', (float) $filters['min_result'])
            )
            ->when(
                isset($filters['max_result']),
                fn($qq) => $qq->where('exam_attempts.result', '<=', (float) $filters['max_result'])
            )
            // Use created_at instead of submitted_at
            ->when(
                !empty($filters['submitted_from']),
                fn($qq) => $qq->whereDate('exam_attempts.created_at', '>=', $filters['submitted_from'])
            )
            ->when(
                !empty($filters['submitted_to']),
                fn($qq) => $qq->whereDate('exam_attempts.created_at', '<=', $filters['submitted_to'])
            );

        // Sorting (use created_at)
        switch ($filters['sort'] ?? 'newest') {
            case 'oldest':
                $q->orderBy('exam_attempts.created_at', 'asc')->orderBy('exam_attempts.id', 'asc');
                break;
            case 'highest_result':
                $q->orderByDesc(DB::raw('COALESCE(exam_attempts.result,0)'));
                break;
            case 'lowest_result':
                $q->orderBy(DB::raw('COALESCE(exam_attempts.result,0)'));
                break;
            default:
                $q->orderBy('exam_attempts.created_at', 'desc')->orderBy('exam_attempts.id', 'desc');
        }

        // Fetch attempts + base exam info (alias created_at as submitted_at)
        $rows = $q->get([
            'exam_attempts.id',
            'exam_attempts.exam_id',
            'exam_attempts.status',
            'exam_attempts.result',
            DB::raw('exam_attempts.created_at as submitted_at'),
            // 🔁 teacher belongs to attempt, not exam
            'exam_attempts.teacher_id as a_teacher_id',
            'exams.id as e_id',
            'exams.name as exam_name',
            'exams.semester_id as e_semester_id',
            'exams.subject_id as e_subject_id',
        ]);

        // Lookup relations in batch
        $subjects = DB::table('subjects')->whereIn('id', $rows->pluck('e_subject_id')->filter()->unique())->get(['id', 'name'])->keyBy('id');
        $semesters = DB::table('semesters')->whereIn('id', $rows->pluck('e_semester_id')->unique())->get(['id', 'name'])->keyBy('id');
        $teacherIds = $rows->pluck('a_teacher_id')->filter()->unique(); // <- from attempts
        $teachers = DB::table('teachers')->whereIn('id', $teacherIds)->get(['id', 'user_id'])->keyBy('id');
        $userIds = $teachers->pluck('user_id')->filter()->unique();
        $users = DB::table('users')->whereIn('id', $userIds)->get(['id', 'first_name', 'last_name', 'email'])->keyBy('id');

        // Aggregates (respect filters) — use created_at for date range
        $scope = ExamAttempt::query()
            ->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
            ->where('exam_attempts.student_id', $student->id)
            ->whereIn('exams.semester_id', $semesterIds)
            ->when(
                !empty($filters['semester_id']) && $semesterIds->contains((int) $filters['semester_id']),
                fn($qq) => $qq->where('exams.semester_id', (int) $filters['semester_id'])
            )
            ->when(
                !empty($filters['exam_id']),
                fn($qq) => $qq->where('exam_attempts.exam_id', (int) $filters['exam_id'])
            )
            ->when(
                !empty($filters['subject_id']),
                fn($qq) => $qq->where('exams.subject_id', (int) $filters['subject_id'])
            )
            // ✅ teacher filter on attempts
            ->when(
                !empty($filters['teacher_id']),
                fn($qq) => $qq->where('exam_attempts.teacher_id', (int) $filters['teacher_id'])
            )
            ->when(
                !empty($filters['status']),
                fn($qq) => $qq->where('exam_attempts.status', $filters['status'])
            )
            ->when(
                isset($filters['min_result']),
                fn($qq) => $qq->where('exam_attempts.result', '>=', (float) $filters['min_result'])
            )
            ->when(
                isset($filters['max_result']),
                fn($qq) => $qq->where('exam_attempts.result', '<=', (float) $filters['max_result'])
            )
            ->when(
                !empty($filters['submitted_from']),
                fn($qq) => $qq->whereDate('exam_attempts.created_at', '>=', $filters['submitted_from'])
            )
            ->when(
                !empty($filters['submitted_to']),
                fn($qq) => $qq->whereDate('exam_attempts.created_at', '<=', $filters['submitted_to'])
            );

        $totalCount = (int) (clone $scope)->count();
        $approvedCount = (int) (clone $scope)->where('exam_attempts.status', 'approved')->count();
        $pendingCount = (int) (clone $scope)->where('exam_attempts.status', 'wait')->count();
        $avgResult = (float) (clone $scope)->avg('exam_attempts.result');
        $bestRow = (clone $scope)->orderByDesc('exam_attempts.result')->first();
        $lastRow = (clone $scope)->orderByDesc('exam_attempts.id')->first();

        // Transform list
        $items = $rows->map(function ($r) use ($subjects, $semesters, $teachers, $users) {
            $teacher = $r->a_teacher_id ? ($teachers[$r->a_teacher_id] ?? null) : null;
            $teacherU = $teacher ? ($users[$teacher->user_id] ?? null) : null;

            return [
                'attempt_id' => (int) $r->id,
                'status' => $r->status, // approved | wait
                'result' => (float) $r->result,
                'submitted_at' => $r->submitted_at, // from created_at alias
                'exam' => [
                    'id' => (int) $r->e_id,
                    'name' => $r->exam_name,
                    'semester' => ($s = $semesters[$r->e_semester_id] ?? null) ? ['id' => (int) $s->id, 'name' => $s->name] : null,
                    'subject' => ($sub = $subjects[$r->e_subject_id] ?? null) ? ['id' => (int) $sub->id, 'name' => $sub->name] : null,
                    // Keep teacher info in payload; source is attempt.teacher_id
                    'teacher' => $teacherU ? [
                        'id' => (int) $r->a_teacher_id,
                        'name' => trim(($teacherU->first_name ?? '') . ' ' . ($teacherU->last_name ?? '')),
                        'email' => $teacherU->email ?? null,
                    ] : null,
                ],
            ];
        });

        return [
            'ok' => true,
            'message' => __('mobile/student/exams.success.loaded'),
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->user ? ($student->user->first_name . ' ' . $student->user->last_name) : null,
                ],
                'year' => ['id' => $activeYear->id, 'name' => $activeYear->name],
                'summary' => [
                    'total' => $totalCount,
                    'approved' => $approvedCount,
                    'pending' => $pendingCount,
                    'avg_result' => $avgResult,
                    'best' => $bestRow ? [
                        'attempt_id' => (int) $bestRow->id,
                        'result' => (float) $bestRow->result,
                    ] : null,
                    'last' => $lastRow ? [
                        'attempt_id' => (int) $lastRow->id,
                        'result' => (float) $lastRow->result,
                        'submitted_at' => optional($lastRow)->created_at,
                    ] : null,
                ],
                'attempts' => $items,   // no pagination
                'count' => $items->count(),
            ],
        ];
    }
}