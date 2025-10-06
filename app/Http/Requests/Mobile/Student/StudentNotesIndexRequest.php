<?php

namespace App\Http\Requests\Mobile\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentNotesIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['pending', 'approved', 'dismissed'])],
            'type' => ['nullable', Rule::in(['positive', 'negative'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'highest_value', 'lowest_value'])],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => __('mobile/student/notes.validation.status.in'),
            'type.in' => __('mobile/student/notes.validation.type.in'),
            'sort.in' => __('mobile/student/notes.validation.sort.in'),
            'semester_id.integer' => __('mobile/student/notes.validation.semester_id.integer'),
            'semester_id.exists' => __('mobile/student/notes.validation.semester_id.exists'),
        ];
    }
}
