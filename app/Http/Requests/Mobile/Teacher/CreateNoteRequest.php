<?php

namespace App\Http\Requests\Mobile\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => 'required|exists:students,id',
            'type' => 'required|string|in:positive,negative',
            'reason' => 'required|string|min:4|max:255',
            'value' => 'required|numeric|min:1|max:100'
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => __('mobile/teacher/notes.student_required'),
            'student_id.exists' => __('mobile/teacher/notes.student_exists'),
            'type.required' => __('mobile/teacher/notes.type_required'),
            'type.string' => __('mobile/teacher/notes.type_string'),
            'type.in' => __('mobile/teacher/notes.type_in'),
            'reason.required' => __('mobile/teacher/notes.reason_required'),
            'reason.string' => __('mobile/teacher/notes.reason_string'),
            'reason.max' => __('mobile/teacher/notes.reason_max'),
            'reason.min' => __('mobile/teacher/notes.reason_min'),
            'value.required' => __('mobile/supervisor/notes.validation.value_required'),
            'value.numeric' => __('mobile/supervisor/notes.validation.value_numeric'),
            'value.min' => __('mobile/supervisor/notes.validation.value_min'),
            'value.max' => __('mobile/supervisor/notes.validation.value_max'),
        ];
    }
}
