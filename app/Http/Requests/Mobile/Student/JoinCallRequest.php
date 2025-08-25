<?php

namespace App\Http\Requests\Mobile\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
class JoinCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    protected function prepareForValidation(): void
    {
        Log::info('JoinCallRequest incoming', [
            'call_id' => $this->input('call_id'),
            'user_id' => optional($this->user())->id,
            'ip' => $this->ip(),
        ]);
    }

    public function rules(): array
    {
        return [
            'call_id' => ['required', 'integer', 'exists:calls,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'call_id.required' => __('mobile/student/call.validation.call_id_required'),
            'call_id.integer' => __('mobile/student/call.validation.call_id_integer'),
            'call_id.exists' => __('mobile/student/call.validation.call_exists'),
        ];
    }
}
