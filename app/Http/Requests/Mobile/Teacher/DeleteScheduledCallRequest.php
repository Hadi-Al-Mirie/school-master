<?php

namespace App\Http\Requests\Mobile\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class DeleteScheduledCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route group ensures IsTeacher; ownership is checked in controller/service.
        return true;
    }

    public function rules(): array
    {
        // No body fields to validate; path param handled by route model binding.
        return [];
    }

    public function messages(): array
    {
        return [];
    }
}
