<?php

namespace App\Services\Mobile;

use App\Models\QuizAttempt;
use App\Models\Quiz;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Support\Facades\DB;

class StudentQuizService
{
    /**
     * List student's quiz attempts for the current active year with aggregates (no pagination).
     *
     * @param  int   $userId
     * @param  array $filters [semester_id, quiz_id, subject_id, teacher_id, min_score, max_score, submitted_from, submitted_to, sort]
     * @return array{ok:bool,message:string,data?:array}
     */
    public function index(int $userId, array $filters = []): array
    {
        // Resolve student
        $student = Student::with('user:id,first_name,last_name')
            ->where('user_id', $userId)
            ->first();

        if (!$student) {
            return ['ok' => false, 'message' => __('mobile/student/quizzes.errors.student_not_found')];
        }

        // Resolve active year and its semesters
        $activeYear = Year::where('is_active', true)->first();
        if (!$activeYear) {
            return ['ok' => false, 'message' => __('mobile/student/quizzes.errors.active_year_not_found')];
        }

        $semesterIds = Semester::where('year_id', $activeYear->id)->pluck('id');

        // Base query – join quizzes to scope by active-year semesters
        $q = QuizAttempt::query()
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quiz_attempts.student_id', $student->id)
            ->whereIn('quizzes.semester_id', $semesterIds)
            ->when(!empty($filters['semester_id']) && $semesterIds->contains((int) $filters['semester_id']), fn($qq) => $qq->where('quizzes.semester_id', (int) $filters['semester_id']))
            ->when(!empty($filters['quiz_id']), fn($qq) => $qq->where('quiz_attempts.quiz_id', (int) $filters['quiz_id']))
            ->when(!empty($filters['subject_id']), fn($qq) => $qq->where('quizzes.subject_id', (int) $filters['subject_id']))
            ->when(!empty($filters['teacher_id']), fn($qq) => $qq->where('quizzes.teacher_id', (int) $filters['teacher_id']))
            ->when(isset($filters['min_score']), fn($qq) => $qq->where('quiz_attempts.total_score', '>=', (float) $filters['min_score']))
            ->when(isset($filters['max_score']), fn($qq) => $qq->where('quiz_attempts.total_score', '<=', (float) $filters['max_score']))
            ->when(!empty($filters['submitted_from']), fn($qq) => $qq->whereDate('quiz_attempts.submitted_at', '>=', $filters['submitted_from']))
            ->when(!empty($filters['submitted_to']), fn($qq) => $qq->whereDate('quiz_attempts.submitted_at', '<=', $filters['submitted_to']));

        // Sorting
        switch ($filters['sort'] ?? 'newest') {
            case 'oldest':
                $q->orderBy('quiz_attempts.submitted_at', 'asc')->orderBy('quiz_attempts.id', 'asc');
                break;
            case 'highest_score':
                $q->orderByDesc(DB::raw('COALESCE(quiz_attempts.total_score,0)'));
                break;
            case 'lowest_score':
                $q->orderBy(DB::raw('COALESCE(quiz_attempts.total_score,0)'));
                break;
            default:
                $q->orderBy('quiz_attempts.submitted_at', 'desc')->orderBy('quiz_attempts.id', 'desc');
        }

        // Fetch list with eager data via select
        $attempts = $q->get([
            'quiz_attempts.id',
            'quiz_attempts.quiz_id',
            'quiz_attempts.total_score',
            'quiz_attempts.submitted_at',
            'quizzes.id as q_id',
            'quizzes.name as quiz_name',
            'quizzes.semester_id as q_semester_id',
            'quizzes.subject_id as q_subject_id',
            'quizzes.teacher_id as q_teacher_id',
        ]);

        // Build lookup data for quiz relations (subject, semester, teacher user) in one shot
        $quizIds = $attempts->pluck('q_id')->unique()->values();

        // Subjects
        $subjects = DB::table('subjects')->whereIn('id', $attempts->pluck('q_subject_id')->filter()->unique())->get(['id', 'name'])->keyBy('id');

        // Semesters
        $semesters = DB::table('semesters')->whereIn('id', $attempts->pluck('q_semester_id')->unique())->get(['id', 'name'])->keyBy('id');

        // Teachers + users
        $teacherIds = $attempts->pluck('q_teacher_id')->filter()->unique();
        $teachers = DB::table('teachers')->whereIn('id', $teacherIds)->get(['id', 'user_id'])->keyBy('id');
        $userIds = $teachers->pluck('user_id')->unique()->filter();
        $users = DB::table('users')->whereIn('id', $userIds)->get(['id', 'first_name', 'last_name', 'email'])->keyBy('id');

        // Aggregates (respect the same filters)
        $scope = QuizAttempt::query()
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quiz_attempts.student_id', $student->id)
            ->whereIn('quizzes.semester_id', $semesterIds)
            ->when(!empty($filters['semester_id']) && $semesterIds->contains((int) $filters['semester_id']), fn($qq) => $qq->where('quizzes.semester_id', (int) $filters['semester_id']))
            ->when(!empty($filters['quiz_id']), fn($qq) => $qq->where('quiz_attempts.quiz_id', (int) $filters['quiz_id']))
            ->when(!empty($filters['subject_id']), fn($qq) => $qq->where('quizzes.subject_id', (int) $filters['subject_id']))
            ->when(!empty($filters['teacher_id']), fn($qq) => $qq->where('quizzes.teacher_id', (int) $filters['teacher_id']))
            ->when(isset($filters['min_score']), fn($qq) => $qq->where('quiz_attempts.total_score', '>=', (float) $filters['min_score']))
            ->when(isset($filters['max_score']), fn($qq) => $qq->where('quiz_attempts.total_score', '<=', (float) $filters['max_score']))
            ->when(!empty($filters['submitted_from']), fn($qq) => $qq->whereDate('quiz_attempts.submitted_at', '>=', $filters['submitted_from']))
            ->when(!empty($filters['submitted_to']), fn($qq) => $qq->whereDate('quiz_attempts.submitted_at', '<=', $filters['submitted_to']));

        $attemptsCount = (int) (clone $scope)->count();
        $avgScore = (float) (clone $scope)->avg('quiz_attempts.total_score');
        $sumScore = (float) (clone $scope)->sum('quiz_attempts.total_score');
        $bestRow = (clone $scope)->orderByDesc('quiz_attempts.total_score')->first();
        $lastRow = (clone $scope)->orderByDesc('quiz_attempts.id')->first();

        // Transform items
        $items = $attempts->map(function ($row) use ($subjects, $semesters, $teachers, $users) {
            $teacher = $row->q_teacher_id ? ($teachers[$row->q_teacher_id] ?? null) : null;
            $teacherUser = $teacher ? ($users[$teacher->user_id] ?? null) : null;

            return [
                'attempt_id' => (int) $row->id,
                'score' => (float) $row->total_score,
                'submitted_at' => $row->submitted_at,
                'quiz' => [
                    'id' => (int) $row->q_id,
                    'name' => $row->quiz_name,
                    'semester' => ($s = $semesters[$row->q_semester_id] ?? null) ? ['id' => (int) $s->id, 'name' => $s->name] : null,
                    'subject' => ($sub = $subjects[$row->q_subject_id] ?? null) ? ['id' => (int) $sub->id, 'name' => $sub->name] : null,
                    'teacher' => $teacherUser ? [
                        'id' => (int) $row->q_teacher_id,
                        'name' => trim(($teacherUser->first_name ?? '') . ' ' . ($teacherUser->last_name ?? '')),
                        'email' => $teacherUser->email ?? null,
                    ] : null,
                ],
            ];
        });

        return [
            'ok' => true,
            'message' => __('mobile/student/quizzes.success.loaded'),
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->user ? ($student->user->first_name . ' ' . $student->user->last_name) : null,
                ],
                'year' => ['id' => $activeYear->id, 'name' => $activeYear->name],
                'summary' => [
                    'attempts' => $attemptsCount,
                    'avg_score' => $avgScore,
                    'sum_score' => $sumScore,
                    'best' => $bestRow ? [
                        'attempt_id' => (int) $bestRow->id,
                        'score' => (float) $bestRow->total_score,
                    ] : null,
                    'last' => $lastRow ? [
                        'attempt_id' => (int) $lastRow->id,
                        'score' => (float) $lastRow->total_score,
                        'submitted_at' => $lastRow->submitted_at,
                    ] : null,
                ],
                'attempts' => $items, // no pagination
                'count' => $items->count(),
            ],
        ];
    }
}