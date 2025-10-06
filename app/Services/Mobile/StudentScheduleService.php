<?php

namespace App\Services\Mobile;

use App\Models\SectionSchedule;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class StudentScheduleService
{
    public function weekly(int $userId): array
    {
        $student = Student::with(['user:id,first_name,last_name', 'section:id,name', 'classroom:id,name'])
            ->where('user_id', $userId)
            ->first();

        if (!$student) {
            return ['ok' => false, 'message' => __('mobile/student/schedule.errors.student_not_found')];
        }
        if (!$student->section_id) {
            return ['ok' => false, 'message' => __('mobile/student/schedule.errors.no_section')];
        }
        $dayOrder = [
            'saturday' => 1,
            'sunday' => 2,
            'monday' => 3,
            'tuesday' => 4,
            'wednesday' => 5,
            'thursday' => 6,
        ];
        $dayLabels = [
            'saturday' => __('mobile/student/schedule.days.saturday'),
            'sunday' => __('mobile/student/schedule.days.sunday'),
            'monday' => __('mobile/student/schedule.days.monday'),
            'tuesday' => __('mobile/student/schedule.days.tuesday'),
            'wednesday' => __('mobile/student/schedule.days.wednesday'),
            'thursday' => __('mobile/student/schedule.days.thursday'),
        ];
        $rows = SectionSchedule::query()
            ->where('section_schedules.section_id', $student->section_id)
            ->leftJoin('periods', 'periods.id', '=', 'section_schedules.period_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'section_schedules.subject_id')
            ->leftJoin('teachers', 'teachers.id', '=', 'section_schedules.teacher_id')
            ->leftJoin('users', 'users.id', '=', 'teachers.user_id')
            ->select([
                'section_schedules.id as schedule_id',
                'section_schedules.day_of_week',
                'periods.id as period_id',
                'periods.name as period_name',
                DB::raw('periods.`order` as period_order'),
                'periods.start_time',
                'periods.end_time',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'teachers.id as teacher_id',
                DB::raw("CONCAT(COALESCE(users.first_name,''),' ',COALESCE(users.last_name,'')) as teacher_name"),
                'users.email as teacher_email',
            ])
            ->get()
            ->map(function ($r) use ($dayOrder) {
                $dayKey = strtolower($r->day_of_week);
                return [
                    'day_key' => $dayKey,
                    'day_order' => $dayOrder[$dayKey] ?? 99,
                    'schedule_id' => (int) $r->schedule_id,
                    'period' => [
                        'id' => $r->period_id ? (int) $r->period_id : null,
                        'name' => $r->period_name,
                        'order' => $r->period_order !== null ? (int) $r->period_order : null,
                        'start_time' => $r->start_time,
                        'end_time' => $r->end_time,
                    ],
                    'subject' => $r->subject_id ? ['id' => (int) $r->subject_id, 'name' => $r->subject_name] : null,
                    'teacher' => $r->teacher_id ? [
                        'id' => (int) $r->teacher_id,
                        'name' => trim($r->teacher_name ?? ''),
                        'email' => $r->teacher_email,
                    ] : null,
                ];
            });
        $grouped = $rows->groupBy('day_key');
        $orderedDays = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];
        $week = [];
        foreach ($orderedDays as $key) {
            $items = ($grouped[$key] ?? collect())
                ->sortBy([
                    ['period.order', 'asc'],
                    ['period.start_time', 'asc'],
                ])->values();
            $week[] = [
                'day' => $key,
                'label' => $dayLabels[$key] ?? ucfirst($key),
                'count' => $items->count(),
                'items' => $items,
            ];
        }
        return [
            'ok' => true,
            'message' => __('mobile/student/schedule.success.loaded'),
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->user ? ($student->user->first_name . ' ' . $student->user->last_name) : null,
                    'section' => $student->section?->name,
                    'classroom' => $student->classroom?->name,
                ],
                'week' => $week,
                'total' => $rows->count(),
            ],
        ];
    }
}
