<?php

namespace App\Http\Requests\Mobile\Supervisor;

use Illuminate\Foundation\Http\FormRequest;

class CreateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Supervisor middleware should guard route; allow to pass to controller for final checks
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'type' => ['required', 'string', 'in:positive,negative'],
            'reason' => ['required', 'string', 'min:4', 'max:255'],
            'value' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => __('mobile/supervisor/notes.validation.student_required'),
            'student_id.integer' => __('mobile/supervisor/notes.validation.student_integer'),
            'student_id.exists' => __('mobile/supervisor/notes.validation.student_exists'),

            'type.required' => __('mobile/supervisor/notes.validation.type_required'),
            'type.in' => __('mobile/supervisor/notes.validation.type_in'),

            'reason.required' => __('mobile/supervisor/notes.validation.reason_required'),
            'reason.string' => __('mobile/supervisor/notes.validation.reason_string'),
            'reason.min' => __('mobile/supervisor/notes.validation.reason_min'),
            'reason.max' => __('mobile/supervisor/notes.validation.reason_max'),

            'value.required' => __('mobile/supervisor/notes.validation.value_required'),
            'value.numeric' => __('mobile/supervisor/notes.validation.value_numeric'),
            'value.min' => __('mobile/supervisor/notes.validation.value_min'),
            'value.max' => __('mobile/supervisor/notes.validation.value_max'),

            'course_id.integer' => __('mobile/supervisor/notes.validation.course_integer'),
            'course_id.exists' => __('mobile/supervisor/notes.validation.course_exists'),

            'status.in' => __('mobile/supervisor/notes.validation.status_in'),
        ];
    }
}