<?php

namespace App\Services\Mobile;

use App\Models\Attendance;
use App\Models\Semester;
use Illuminate\Support\Facades\Auth;

class AttendanceService
{
    public function activeSemester(): ?Semester
    {
        return Semester::where('is_active', true)->first();
    }

    /**
     * Create attendance by a supervisor (current user).
     *
     * @param  array{type:string, attendable_id:int, attendance_type_id:int, att_date:string, justification:?string} $payload
     */
    public function createBySupervisor(array $payload): Attendance
    {
        $semester = $this->activeSemester();
        if (!$semester) {
            throw new \RuntimeException(__('mobile/supervisor/attendance.errors.no_active_semester'));
        }

        $fqcn = $payload['type'] === 'student' ? \App\Models\Student::class : \App\Models\Teacher::class;

        return Attendance::create([
            'attendable_type' => $fqcn,
            'attendable_id' => $payload['attendable_id'],
            'attendance_type_id' => $payload['attendance_type_id'],
            'by_id' => Auth::id(),
            'semester_id' => $semester->id,
            'att_date' => $payload['att_date'],
            'justification' => $payload['justification'] ?? null,
        ]);
    }
}