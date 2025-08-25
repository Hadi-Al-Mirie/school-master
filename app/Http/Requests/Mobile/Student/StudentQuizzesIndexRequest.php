<?php

namespace App\Http\Requests\Mobile\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentQuizzesIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protected by IsStudent middleware
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'quiz_id' => ['nullable', 'integer', 'exists:quizzes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'min_score' => ['nullable', 'numeric', 'min:0'],
            'max_score' => ['nullable', 'numeric', 'min:0', 'gte:min_score'],
            'submitted_from' => ['nullable', 'date'],
            'submitted_to' => ['nullable', 'date', 'after_or_equal:submitted_from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'highest_score', 'lowest_score'])],
        ];
    }

    public function messages(): array
    {
        return [
            'semester_id.integer' => __('mobile/student/quizzes.validation.semester_id.integer'),
            'semester_id.exists' => __('mobile/student/quizzes.validation.semester_id.exists'),
            'quiz_id.integer' => __('mobile/student/quizzes.validation.quiz_id.integer'),
            'quiz_id.exists' => __('mobile/student/quizzes.validation.quiz_id.exists'),
            'subject_id.integer' => __('mobile/student/quizzes.validation.subject_id.integer'),
            'subject_id.exists' => __('mobile/student/quizzes.validation.subject_id.exists'),
            'teacher_id.integer' => __('mobile/student/quizzes.validation.teacher_id.integer'),
            'teacher_id.exists' => __('mobile/student/quizzes.validation.teacher_id.exists'),
            'min_score.numeric' => __('mobile/student/quizzes.validation.min_score.numeric'),
            'min_score.min' => __('mobile/student/quizzes.validation.min_score.min'),
            'max_score.numeric' => __('mobile/student/quizzes.validation.max_score.numeric'),
            'max_score.min' => __('mobile/student/quizzes.validation.max_score.min'),
            'max_score.gte' => __('mobile/student/quizzes.validation.max_score.gte'),
            'submitted_from.date' => __('mobile/student/quizzes.validation.submitted_from.date'),
            'submitted_to.date' => __('mobile/student/quizzes.validation.submitted_to.date'),
            'submitted_to.after_or_equal' => __('mobile/student/quizzes.validation.submitted_to.after_or_equal'),
            'sort.in' => __('mobile/student/quizzes.validation.sort.in'),
        ];
    }
}