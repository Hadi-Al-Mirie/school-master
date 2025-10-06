<?php

namespace App\Http\Requests\Mobile\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class ScheduleCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:30', 'max:720'],
            'channel_name' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function messages(): array
    {
        return [
            'section_id.required' => __('mobile/teacher/call.validation.section_required'),
            'section_id.integer' => __('mobile/teacher/call.validation.section_integer'),
            'section_id.exists' => __('mobile/teacher/call.validation.section_exists'),

            'subject_id.required' => __('mobile/teacher/call.validation.subject_required'),
            'subject_id.integer' => __('mobile/teacher/call.validation.subject_integer'),
            'subject_id.exists' => __('mobile/teacher/call.validation.subject_exists'),

            'scheduled_at.required' => __('mobile/teacher/call.validation.scheduled_required'),
            'scheduled_at.date' => __('mobile/teacher/call.validation.scheduled_date'),
            'scheduled_at.after' => __('mobile/teacher/call.validation.scheduled_after_now'),

            'duration_minutes.integer' => __('mobile/teacher/call.validation.duration_integer'),
            'duration_minutes.min' => __('mobile/teacher/call.validation.duration_min'),
            'duration_minutes.max' => __('mobile/teacher/call.validation.duration_max'),

            'channel_name.string' => __('mobile/teacher/call.validation.channel_string'),
            'channel_name.max' => __('mobile/teacher/call.validation.channel_max'),
        ];
    }
}