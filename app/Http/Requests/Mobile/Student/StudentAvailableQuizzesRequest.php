<?php

namespace App\Http\Requests\Mobile\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentAvailableQuizzesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'ending_soon'])],
        ];
    }

    public function messages(): array
    {
        return [
            'subject_id.integer' => __('mobile/student/quiz_submission.validation.subject_id.integer'),
            'subject_id.exists' => __('mobile/student/quiz_submission.validation.subject_id.exists'),
            'sort.in' => __('mobile/student/quiz_submission.validation.sort.in'),
        ];
    }
}
