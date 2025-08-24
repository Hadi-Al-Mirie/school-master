<?php

namespace App\Http\Requests\Mobile\Supervisor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListStageNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('status')) {
            $this->merge(['status' => 'pending']);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['pending', 'approved', 'dismissed', 'all'])],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => __('mobile/supervisor/notes.validation.status.required'),
            'status.in' => __('mobile/supervisor/notes.validation.status.in'),
        ];
    }
}
