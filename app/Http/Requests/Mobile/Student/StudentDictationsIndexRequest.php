<?php

namespace App\Http\Requests\Mobile\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentDictationsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'highest_result', 'lowest_result'])],
        ];
    }

    public function messages(): array
    {
        return [
            'semester_id.integer' => __('mobile/student/dictations.validation.semester_id.integer'),
            'semester_id.exists' => __('mobile/student/dictations.validation.semester_id.exists'),
            'teacher_id.integer' => __('mobile/student/dictations.validation.teacher_id.integer'),
            'teacher_id.exists' => __('mobile/student/dictations.validation.teacher_id.exists'),
            'section_id.integer' => __('mobile/student/dictations.validation.section_id.integer'),
            'section_id.exists' => __('mobile/student/dictations.validation.section_id.exists'),
            'sort.in' => __('mobile/student/dictations.validation.sort.in'),
        ];
    }
}
