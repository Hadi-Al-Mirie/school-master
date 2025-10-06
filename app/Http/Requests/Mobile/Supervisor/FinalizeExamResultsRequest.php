<?php

namespace App\Http\Requests\Mobile\Supervisor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinalizeExamResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attempts' => ['required', 'array', 'min:1'],
            'attempts.*.attempt_id' => ['required', 'integer', 'exists:exam_attempts,id'],
            'attempts.*.result' => ['required', 'numeric', 'min:0'],
            'approve' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'attempts.required' => __('mobile/supervisor/exam_approval.validation.attempts.required'),
            'attempts.array' => __('mobile/supervisor/exam_approval.validation.attempts.array'),
            'attempts.min' => __('mobile/supervisor/exam_approval.validation.attempts.min'),

            'attempts.*.attempt_id.required' => __('mobile/supervisor/exam_approval.validation.attempt_id.required'),
            'attempts.*.attempt_id.integer' => __('mobile/supervisor/exam_approval.validation.attempt_id.integer'),
            'attempts.*.attempt_id.exists' => __('mobile/supervisor/exam_approval.validation.attempt_id.exists'),

            'attempts.*.result.required' => __('mobile/supervisor/exam_approval.validation.result.required'),
            'attempts.*.result.numeric' => __('mobile/supervisor/exam_approval.validation.result.numeric'),
            'attempts.*.result.min' => __('mobile/supervisor/exam_approval.validation.result.min'),

            'approve.boolean' => __('mobile/supervisor/exam_approval.validation.approve.boolean'),
        ];
    }
}