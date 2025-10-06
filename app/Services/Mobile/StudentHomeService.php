<?php

namespace App\Services\Mobile;

use App\Models\Attendance;
use App\Models\Dictation;
use App\Models\ExamAttempt;
use App\Models\Note;
use App\Models\QuizAttempt;
use App\Models\SectionSchedule;
use App\Models\Semester;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StudentHomeService
{
    public function summary(int $userId, ?int $semesterId = null): array
    {
        $student = Student::with(['user', 'stage', 'classroom', 'section'])
            ->where('user_id', $userId)
            ->first();
        if (!$student) {
            return [
                'ok' => false,
                'message' => trans('mobile/student/home.errors.student_not_found'),
                'data' => null,
            ];
        }
        $semester = $semesterId
            ? Semester::find($semesterId)
            : Semester::where('is_active', true)->first();
        if (!$semester) {
            return [
                'ok' => false,
                'message' => trans('mobile/student/home.errors.semester_not_found'),
                'data' => null,
            ];
        }
        $approvedNotes = Note::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->where('status', 'approved');
        $positiveTotal = (clone $approvedNotes)->where('type', 'positive')->sum('value');
        $negativeTotal = (clone $approvedNotes)->where('type', 'negative')->sum('value');
        $netPoints = max(0, ($positiveTotal - $negativeTotal) - (float) $student->cashed_points);
        $notesCounts = Note::select('status', DB::raw('COUNT(*) as cnt'))
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->groupBy('status')
            ->pluck('cnt', 'status');
        $attendanceByType = Attendance::query()
            ->join('attendance_types', 'attendance_types.id', '=', 'attendances.attendance_type_id')
            ->where('attendances.attendable_type', Student::class)
            ->where('attendances.attendable_id', $student->id)
            ->where('attendances.semester_id', $semester->id)
            ->select('attendance_types.id', 'attendance_types.name', DB::raw('COUNT(*) as count'))
            ->groupBy('attendance_types.id', 'attendance_types.name')
            ->get();
        $attendanceTotal = $attendanceByType->sum('count');
        $attendanceScore = Attendance::query()
            ->join('attendance_types', 'attendance_types.id', '=', 'attendances.attendance_type_id')
            ->where('attendances.attendable_type', Student::class)
            ->where('attendances.attendable_id', $student->id)
            ->where('attendances.semester_id', $semester->id)
            ->sum('attendance_types.value');
        $dictationQuery = Dictation::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id);
        $dictations = [
            'count' => (clone $dictationQuery)->count(),
            'avg' => (float) (clone $dictationQuery)->avg('result'),
            'last' => optional((clone $dictationQuery)->latest('id')->first(), function ($d) {
                return ['id' => $d->id, 'result' => (float) $d->result, 'created_at' => $d->created_at];
            }),
        ];
        $quizAttemptQuery = QuizAttempt::query()
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quiz_attempts.student_id', $student->id)
            ->where('quizzes.semester_id', $semester->id);
        $quizzes = [
            'attempts' => (clone $quizAttemptQuery)->count(),
            'avg_score' => (float) (clone $quizAttemptQuery)->avg('quiz_attempts.total_score'),
            'recent' => (clone $quizAttemptQuery)
                ->orderByDesc('quiz_attempts.id')
                ->limit(3)
                ->get(['quiz_attempts.id', 'quizzes.name as quiz_name', 'quiz_attempts.total_score', 'quiz_attempts.submitted_at']),
        ];
        $examAttemptQuery = ExamAttempt::query()
            ->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
            ->where('exam_attempts.student_id', $student->id)
            ->where('exams.semester_id', $semester->id);
        $exams = [
            'approved_count' => (clone $examAttemptQuery)->where('exam_attempts.status', 'approved')->count(),
            'pending_count' => (clone $examAttemptQuery)->where('exam_attempts.status', 'wait')->count(),
            'avg_result' => (float) (clone $examAttemptQuery)->where('exam_attempts.status', 'approved')->avg('exam_attempts.result'),
            'best' => optional((clone $examAttemptQuery)->where('exam_attempts.status', 'approved')->orderByDesc('exam_attempts.result')->first(), function ($ea) {
                return ['exam_attempt_id' => $ea->id, 'result' => (float) $ea->result];
            }),
        ];
        $tz = config('app.timezone', 'Europe/Amsterdam');
        $todayDow = strtolower(Carbon::now($tz)->format('l'));
        $schedule = SectionSchedule::query()
            ->where('section_id', $student->section_id)
            ->where('day_of_week', $todayDow)
            ->leftJoin('periods', 'periods.id', '=', 'section_schedules.period_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'section_schedules.subject_id')
            ->leftJoin('teachers', 'teachers.id', '=', 'section_schedules.teacher_id')
            ->leftJoin('users', 'users.id', '=', 'teachers.user_id')
            ->orderBy('periods.order')
            ->get([
                'section_schedules.id',
                'periods.name as period_name',
                'periods.start_time',
                'periods.end_time',
                'subjects.name as subject_name',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) as teacher_name"),
            ]);
        return [
            'ok' => true,
            'message' => trans('mobile/student/home.success.loaded'),
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->user ? ($student->user->first_name . ' ' . $student->user->last_name) : null,
                    'gender' => $student->gender,
                    'birth_day' => $student->birth_day,
                    'location' => $student->location,
                    'stage' => optional($student->stage)->name,
                    'classroom' => optional($student->classroom)->name,
                    'section' => optional($student->section)->name,
                    'cashed_points' => (float) $student->cashed_points,
                ],
                'semester' => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'is_active' => (bool) $semester->is_active,
                ],
                'points' => [
                    'current' => (float) $netPoints,
                    'positive_total' => (int) $positiveTotal,
                    'negative_total' => (int) $negativeTotal,
                    'cashed_points' => (float) $student->cashed_points,
                ],
                'notes' => [
                    'pending' => (int) ($notesCounts['pending'] ?? 0),
                    'approved' => (int) ($notesCounts['approved'] ?? 0),
                    'dismissed' => (int) ($notesCounts['dismissed'] ?? 0),
                ],
                'attendance' => [
                    'total' => (int) $attendanceTotal,
                    'score' => (int) $attendanceScore,
                    'by_type' => $attendanceByType->map(fn($r) => [
                        'type_id' => (int) $r->id,
                        'name' => $r->name,
                        'count' => (int) $r->count,
                    ]),
                ],
                'dictations' => $dictations,
                'quizzes' => $quizzes,
                'exams' => $exams,
                'today_schedule' => $schedule,
            ],
        ];
    }
}