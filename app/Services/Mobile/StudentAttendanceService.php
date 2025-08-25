<?php

namespace App\Services\Mobile;

use App\Models\Attendance;
use App\Models\AttendanceType;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Support\Facades\DB;

class StudentAttendanceService
{
    /**
     * List student's attendances for the current active year with aggregates (no pagination).
     *
     * @param  int   $userId
     * @param  array $filters [attendance_type_id, semester_id, date_from, date_to, sort]
     * @return array{ok:bool,message:string,data?:array}
     */
    public function index(int $userId, array $filters = []): array
    {
        // Resolve student
        $student = Student::with('user:id,first_name,last_name')
            ->where('user_id', $userId)
            ->first();

        if (!$student) {
            return ['ok' => false, 'message' => __('mobile/student/attendance.errors.student_not_found')];
        }

        // Resolve active year and its semesters
        $activeYear = Year::where('is_active', true)->first();
        if (!$activeYear) {
            return ['ok' => false, 'message' => __('mobile/student/attendance.errors.active_year_not_found')];
        }

        $semesterIds = Semester::where('year_id', $activeYear->id)->pluck('id');

        // Base query for attendances of this student within active year
        $q = Attendance::query()
            ->with([
                'attendanceType:id,name,value',
                'semester:id,name,year_id',
                'createdBy:id,first_name,last_name,email',
            ])
            ->where('attendable_type', Student::class)
            ->where('attendable_id', $student->id)
            ->whereIn('semester_id', $semesterIds);

        // Filters
        if (!empty($filters['attendance_type_id'])) {
            $q->where('attendance_type_id', (int) $filters['attendance_type_id']);
        }

        if (!empty($filters['semester_id']) && $semesterIds->contains((int) $filters['semester_id'])) {
            $q->where('semester_id', (int) $filters['semester_id']);
        }

        if (!empty($filters['date_from'])) {
            $q->whereDate('att_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->whereDate('att_date', '<=', $filters['date_to']);
        }

        // Sorting
        switch ($filters['sort'] ?? 'newest') {
            case 'oldest':
                $q->orderBy('att_date', 'asc')->orderBy('id', 'asc');
                break;
            default:
                $q->orderBy('att_date', 'desc')->orderBy('id', 'desc');
        }

        $attendances = $q->get();

        // Aggregates by type across active year (respecting date/semester filters above)
        $byType = Attendance::query()
            ->join('attendance_types', 'attendance_types.id', '=', 'attendances.attendance_type_id')
            ->where('attendances.attendable_type', Student::class)
            ->where('attendances.attendable_id', $student->id)
            ->whereIn('attendances.semester_id', $semesterIds)
            ->when(!empty($filters['attendance_type_id']), fn($qq) => $qq->where('attendances.attendance_type_id', (int) $filters['attendance_type_id']))
            ->when(!empty($filters['semester_id']) && $semesterIds->contains((int) $filters['semester_id']), fn($qq) => $qq->where('attendances.semester_id', (int) $filters['semester_id']))
            ->when(!empty($filters['date_from']), fn($qq) => $qq->whereDate('attendances.att_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn($qq) => $qq->whereDate('attendances.att_date', '<=', $filters['date_to']))
            ->groupBy('attendance_types.id', 'attendance_types.name', 'attendance_types.value')
            ->get([
                'attendance_types.id as type_id',
                'attendance_types.name as name',
                'attendance_types.value as value',
                DB::raw('COUNT(*) as count'),
            ])
            ->map(function ($r) {
                $r->points = (int) $r->count * (int) $r->value;
                return [
                    'type_id' => (int) $r->type_id,
                    'name' => $r->name,
                    'value' => (int) $r->value,
                    'count' => (int) $r->count,
                    'points' => (int) $r->points,
                ];
            });

        $totalCount = $byType->sum('count');
        $totalPoints = $byType->sum('points');

        // Transform items (list)
        $items = $attendances->map(function (Attendance $a) {
            return [
                'id' => $a->id,
                'date' => $a->att_date,
                'justification' => $a->justification,
                'type' => $a->attendanceType ? [
                    'id' => $a->attendanceType->id,
                    'name' => $a->attendanceType->name,
                    'value' => (int) $a->attendanceType->value,
                ] : null,
                'semester' => $a->semester ? ['id' => $a->semester->id, 'name' => $a->semester->name] : null,
                'created_by' => $a->createdBy ? [
                    'id' => $a->createdBy->id,
                    'name' => trim(($a->createdBy->first_name ?? '') . ' ' . ($a->createdBy->last_name ?? '')),
                    'email' => $a->createdBy->email ?? null,
                ] : null,
                'created_at' => $a->created_at,
            ];
        });

        return [
            'ok' => true,
            'message' => __('mobile/student/attendance.success.loaded'),
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->user ? ($student->user->first_name . ' ' . $student->user->last_name) : null,
                ],
                'year' => ['id' => $activeYear->id, 'name' => $activeYear->name],
                'summary' => [
                    'by_type' => $byType->values(),
                    'totals' => [
                        'count' => (int) $totalCount,
                        'points' => (int) $totalPoints,
                    ],
                ],
                'attendances' => $items, // no pagination
                'count' => $items->count(),
            ],
        ];
    }
}