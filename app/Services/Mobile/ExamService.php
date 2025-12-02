<?php

namespace App\Services\Mobile;

use App\Models\Exam;
use App\Models\Semester;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\Supervisor;
use Illuminate\Support\Facades\Auth;
use App\Models\ExamAttempt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Student;
use App\Models\Year;

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


    public function waitingExamsForSupervisorStage(): Collection
    {
        $supervisor = Supervisor::where('user_id', Auth::id())->first();
        if (!$supervisor) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.supervisor_not_found'));
        }
        return Exam::query()
            ->with(['section:id,name,classroom_id', 'section.classroom:id,name,stage_id', 'subject:id,name'])
            ->where('status', 'wait')
            ->whereHas('section.classroom', function ($q) use ($supervisor) {
                $q->where('stage_id', $supervisor->stage_id);
            })
            ->orderByDesc('id')
            ->get([
                'id',
                'name',
                'status',
                'section_id',
                'subject_id',
                'semester_id',
                'max_result',
                'created_by'
            ]);
    }

    public function teacherEnterable($user): array
    {
        $teacher = $user ? $user->teacher : null;
        if (!$teacher) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/teacher/exam.errors.not_teacher')
                ]
            ];
        }
        $exams = Exam::query()->where('status', Exam::STATUS_WAIT)->whereExists(function ($q) use ($teacher) {
            $q->select(DB::raw(1))->from('section_subjects')->whereColumn('section_subjects.section_id', 'exams.section_id')->whereColumn('section_subjects.subject_id', 'exams.subject_id')->where('section_subjects.teacher_id', $teacher->id);
        })->with(['section', 'subject'])->get()->map(function ($ex) {
            return [
                'id' => $ex->id,
                'name' => $ex->name,
                'classroom' => $ex->section->classroom->name,
                'section_id' => $ex->section->id,
                'section_name' => $ex->section->name,
                'subject_id' => $ex->subject->id,
                'subject_name' => $ex->subject->name,
                'semester' => $ex->semester->name,
                'max_result' => $ex->max_result
            ];
        })->values();
        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => $exams
            ]
        ];
    }
    public function teacherSubmitResults(array $payload, Exam $exam, $user): array
    {
        $teacher = $user ? $user->teacher : null;
        if (!$teacher) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/teacher/exam.errors.not_teacher')
                ]
            ];
        }
        if ($exam->status !== Exam::STATUS_WAIT) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/teacher/exam.errors.exam_not_open')
                ]
            ];
        }
        $assigned = \App\Models\SectionSubject::where('section_id', $exam->section_id)->where('subject_id', $exam->subject_id)->where('teacher_id', $teacher->id)->exists();
        if (!$assigned) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/teacher/exam.errors.teacher_not_assigned')
                ]
            ];
        }
        if ($exam->max_result === null) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/teacher/exam.errors.max_result_not_set')
                ]
            ];
        }
        $maxAllowed = (float) $exam->max_result;
        $results = $payload['results'] ?? [];
        $errors = new \Illuminate\Support\MessageBag();
        foreach ($results as $idx => $row) {
            $studentId = $row['student_id'] ?? null;
            $resultVal = $row['result'] ?? null;
            $student = Student::where('id', $studentId)->where('section_id', $exam->section_id)->first();
            if (!$student) {
                $errors->add("results.$idx.student_id", __('mobile/teacher/exam.errors.student_not_in_section'));
                continue;
            }
            if (!is_numeric($resultVal)) {
                $errors->add("results.$idx.result", __('mobile/teacher/exam.validation.result_numeric'));
                continue;
            }
            if ((float) $resultVal > $maxAllowed) {
                $errors->add("results.$idx.result", __('mobile/teacher/exam.validation.result_max', ['max' => $maxAllowed]));
            }
        }
        if ($errors->any()) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/teacher/exam.validation_failed'),
                    'errors' => $errors->messages()
                ]
            ];
        }
        DB::transaction(function () use ($results, $exam, $teacher, $maxAllowed) {
            foreach ($results as $row) {
                $studentId = $row['student_id'];
                $resultVal = (float) $row['result'];
                ExamAttempt::updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'student_id' => $studentId
                    ],
                    [
                        'teacher_id' => $teacher->id,
                        'result' => $resultVal,
                        'status' => ExamAttempt::STATUS_WAIT,
                        'updated_at' => now()
                    ]
                );
            }
        });
        return [
            'status' => 201,
            'body' => [
                'success' => true,
                'message' => __('mobile/teacher/exam.store.success')
            ]
        ];
    }


    public function examAttempts(Exam $exam, string $status = 'pending'): Collection
    {
        return ExamAttempt::query()
            ->with(['student:id,user_id,section_id', 'student.user:id,first_name,last_name'])
            ->where('exam_id', $exam->id)
            ->orderBy('id')
            ->get(['id', 'exam_id', 'student_id', 'result', 'status', 'created_at']);
    }

    public function finalize(Exam $exam, array $attempts): array
    {
        $supervisor = Supervisor::where('user_id', Auth::id())->first();
        if (!$supervisor) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.supervisor_not_found'));
        }
        if (!$exam->relationLoaded('section')) {
            $exam->load('section.classroom');
        }
        if ((int) $exam->section->classroom->stage_id !== (int) $supervisor->stage_id) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.exam_not_in_stage'));
        }
        if ($exam->status === 'released') {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.exam_already_released'));
        }
        $allAttempts = ExamAttempt::where('exam_id', $exam->id)->get(['id', 'student_id', 'result', 'status']);
        if ($allAttempts->isEmpty()) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.no_attempts'));
        }
        $sectionStudentCount = $exam->section->students()->count();
        if ($sectionStudentCount === 0) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.section_empty'));
        }
        $allById = $allAttempts->keyBy('id');
        foreach ($attempts as $row) {
            if (!isset($allById[$row['attempt_id']])) {
                throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.attempt_not_belong'));
            }
        }
        $max = (float) ($exam->max_result ?? 100);
        foreach ($attempts as $row) {
            $val = (float) $row['result'];
            if ($val < 0 || $val > $max) {
                throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.result_out_of_bounds', ['min' => 0, 'max' => $max]));
            }
            $allById[$row['attempt_id']]->result = $val;
        }
        $distinctStudentsInAttempts = $allAttempts->pluck('student_id')->unique()->count();
        if ($distinctStudentsInAttempts !== $sectionStudentCount) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.incomplete_section'));
        }
        return DB::transaction(function () use ($allAttempts, $allById, $attempts, $exam) {
            foreach ($attempts as $row) {
                $attempt = $allById[$row['attempt_id']];
                $attempt->status = 'approved';
                $attempt->save();
            }
            $pendingOthers = $allAttempts->where('status', 'pending');
            foreach ($pendingOthers as $attempt) {
                $attempt->status = 'approved';
                $attempt->save();
            }
            $exam->status = 'released';
            $exam->save();
            return [
                'approved' => $allAttempts->count(),
                'exam' => [
                    'id' => $exam->id,
                    'status' => $exam->status,
                    'section_id' => $exam->section_id,
                ],
            ];
        });
    }

    public function studentExamsIndex(int $userId, array $filters = []): array
    {
        $student = Student::with('user:id,first_name,last_name')
            ->where('user_id', $userId)
            ->first();
        if (!$student) {
            return ['ok' => false, 'message' => __('mobile/student/exams.errors.student_not_found')];
        }
        $activeYear = Year::where('is_active', true)->first();
        if (!$activeYear) {
            return ['ok' => false, 'message' => __('mobile/student/exams.errors.active_year_not_found')];
        }
        $semesterIds = Semester::where('year_id', $activeYear->id)->pluck('id');
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
        $rows = $q->get([
            'exam_attempts.id',
            'exam_attempts.exam_id',
            'exam_attempts.status',
            'exam_attempts.result',
            DB::raw('exam_attempts.created_at as submitted_at'),
            'exam_attempts.teacher_id as a_teacher_id',
            'exams.id as e_id',
            'exams.name as exam_name',
            'exams.semester_id as e_semester_id',
            'exams.subject_id as e_subject_id',
        ]);
        $subjects = DB::table('subjects')->whereIn('id', $rows->pluck('e_subject_id')->filter()->unique())->get(['id', 'name'])->keyBy('id');
        $semesters = DB::table('semesters')->whereIn('id', $rows->pluck('e_semester_id')->unique())->get(['id', 'name'])->keyBy('id');
        $teacherIds = $rows->pluck('a_teacher_id')->filter()->unique();
        $teachers = DB::table('teachers')->whereIn('id', $teacherIds)->get(['id', 'user_id'])->keyBy('id');
        $userIds = $teachers->pluck('user_id')->filter()->unique();
        $users = DB::table('users')->whereIn('id', $userIds)->get(['id', 'first_name', 'last_name', 'email'])->keyBy('id');
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
        $items = $rows->map(function ($r) use ($subjects, $semesters, $teachers, $users) {
            $teacher = $r->a_teacher_id ? ($teachers[$r->a_teacher_id] ?? null) : null;
            $teacherU = $teacher ? ($users[$teacher->user_id] ?? null) : null;
            return [
                'attempt_id' => (int) $r->id,
                'status' => $r->status,
                'result' => (float) $r->result,
                'submitted_at' => $r->submitted_at,
                'exam' => [
                    'id' => (int) $r->e_id,
                    'name' => $r->exam_name,
                    'semester' => ($s = $semesters[$r->e_semester_id] ?? null) ? ['id' => (int) $s->id, 'name' => $s->name] : null,
                    'subject' => ($sub = $subjects[$r->e_subject_id] ?? null) ? ['id' => (int) $sub->id, 'name' => $sub->name] : null,
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
                'attempts' => $items,
                'count' => $items->count(),
            ],
        ];
    }
}