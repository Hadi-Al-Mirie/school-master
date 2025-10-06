<?php

namespace App\Http\Requests\Mobile\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use App\Models\SectionSubject;
use App\Models\Call;

class CreateCallRequest extends FormRequest
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
            'started_at' => ['required', 'date'],
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
            'started_at.required' => __('mobile/teacher/call.validation.started_required'),
            'started_at.date' => __('mobile/teacher/call.validation.started_date'),
            'channel_name.string' => __('mobile/teacher/call.validation.channel_string'),
            'channel_name.max' => __('mobile/teacher/call.validation.channel_max'),
        ];
    }
}