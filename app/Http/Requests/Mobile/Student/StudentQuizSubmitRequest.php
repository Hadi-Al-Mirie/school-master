<?php

namespace App\Http\Requests\Mobile\Student;

use Illuminate\Foundation\Http\FormRequest;

class StudentQuizSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'exists:quiz_questions,id', 'distinct'],
            'answers.*.answer_id' => ['nullable', 'integer', 'exists:quiz_question_answers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'answers.required' => __('mobile/student/quiz_submission.validation.answers.required'),
            'answers.array' => __('mobile/student/quiz_submission.validation.answers.array'),
            'answers.min' => __('mobile/student/quiz_submission.validation.answers.min'),
            'answers.*.question_id.required' => __('mobile/student/quiz_submission.validation.question_id.required'),
            'answers.*.question_id.integer' => __('mobile/student/quiz_submission.validation.question_id.integer'),
            'answers.*.question_id.exists' => __('mobile/student/quiz_submission.validation.question_id.exists'),
            'answers.*.question_id.distinct' => __('mobile/student/quiz_submission.validation.question_id.distinct'),
            'answers.*.answer_id.integer' => __('mobile/student/quiz_submission.validation.answer_id.integer'),
            'answers.*.answer_id.exists' => __('mobile/student/quiz_submission.validation.answer_id.exists'),
        ];
    }
}