<?php

namespace App\Http\Requests\Dashboard\schedule;

use Illuminate\Foundation\Http\FormRequest;

class GenerateScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'get_all_schedules' => 'boolean',
            'optimize' => 'boolean',
            'max_iterations' => 'nullable|integer|min:1|max:1000',
            'timeout' => 'nullable|integer|min:10|max:3600',
        ];
    }

    /**
     * Prepare and sanitize input before validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'get_all_schedules' => $this->boolean('get_all_schedules', false),
            'optimize' => $this->boolean('optimize', false),
        ]);
    }
}
