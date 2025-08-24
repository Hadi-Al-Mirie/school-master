<?php

namespace App\Http\Requests\Mobile\Student;

use Illuminate\Foundation\Http\FormRequest;

class StudentHomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // IsStudent middleware guards access
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'semester_id.integer' => __('mobile/student/home.validation.semester_id.integer'),
            'semester_id.exists'  => __('mobile/student/home.validation.semester_id.exists'),
        ];
    }
}
