<?php

namespace App\Http\Requests\Mobile\Supervisor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approved', 'dismissed'])],
            'value' => ['nullable', 'required_if:decision,approved', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => __('mobile/supervisor/notes.validation.decision.required'),
            'decision.in' => __('mobile/supervisor/notes.validation.decision.in'),
            'value.required_if' => __('mobile/supervisor/notes.validation.value.required_if'),
            'value.numeric' => __('mobile/supervisor/notes.validation.value.numeric'),
            'value.min' => __('mobile/supervisor/notes.validation.value.min'),
            'value.max' => __('mobile/supervisor/notes.validation.value.max'),
        ];
    }
}
