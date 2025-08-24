<?php

namespace App\Http\Requests\Dashboard\search;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q'    => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['required', 'in:teacher,student,supervisor'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => __('dashboard/search/validation.q.required'),
            'q.string'   => __('dashboard/search/validation.q.string'),
            'q.min'      => __('dashboard/search/validation.q.min'),
            'q.max'      => __('dashboard/search/validation.q.max'),

            'type.required' => __('dashboard/search/validation.type.required'),
            'type.in'       => __('dashboard/search/validation.type.in'),
        ];
    }
}
