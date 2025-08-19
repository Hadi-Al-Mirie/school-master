<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'father_name' => 'nullable|string|max:100',
            'mother_name' => 'nullable|string|max:100',
            'mother_last_name' => 'nullable|string|max:100',
            'class' => 'nullable|string|max:50',
            'gender' => 'nullable|in:male,female',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'address' => 'nullable|string|max:1000',
            'student_mobile' => 'nullable|string|max:20',
            'parent_mobile' => 'nullable|string|max:20',
            'landline' => 'nullable|string|max:20',

        ];
    }
}
