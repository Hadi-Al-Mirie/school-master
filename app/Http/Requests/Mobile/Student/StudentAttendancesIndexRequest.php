<?php

namespace App\Http\Requests\Mobile\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentAttendancesIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is protected by IsStudent middleware
    }

    public function rules(): array
    {
        return [
            'attendance_type_id' => ['nullable', 'integer', 'exists:attendance_types,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
        ];
    }

    public function messages(): array
    {
        return [
            'attendance_type_id.integer' => __('mobile/student/attendance.validation.attendance_type_id.integer'),
            'attendance_type_id.exists' => __('mobile/student/attendance.validation.attendance_type_id.exists'),
            'semester_id.integer' => __('mobile/student/attendance.validation.semester_id.integer'),
            'semester_id.exists' => __('mobile/student/attendance.validation.semester_id.exists'),
            'date_from.date' => __('mobile/student/attendance.validation.date_from.date'),
            'date_to.date' => __('mobile/student/attendance.validation.date_to.date'),
            'date_to.after_or_equal' => __('mobile/student/attendance.validation.date_to.after_or_equal'),
            'sort.in' => __('mobile/student/attendance.validation.sort.in'),
        ];
    }
}