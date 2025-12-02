<?php

namespace App\Http\Requests\Dashboard\Events;

use Illuminate\Foundation\Http\FormRequest;

class EvetStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'notes' => 'required|string|min:2|max:255',
            'title' => 'required|string|max:255',
            'semester_id' => 'required|integer|exists:semesters,id',
        ];
    }
}
