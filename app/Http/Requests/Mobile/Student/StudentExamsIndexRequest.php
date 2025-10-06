<?php

namespace App\Http\Requests\Mobile\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentExamsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'exam_id' => ['nullable', 'integer', 'exists:exams,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'status' => ['nullable', Rule::in(['approved', 'wait'])],
            'min_result' => ['nullable', 'numeric', 'min:0'],
            'max_result' => ['nullable', 'numeric', 'min:0', 'gte:min_result'],
            'submitted_from' => ['nullable', 'date'],
            'submitted_to' => ['nullable', 'date', 'after_or_equal:submitted_from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'highest_result', 'lowest_result'])],
        ];
    }

    public function messages(): array
    {
        return [
            'semester_id.integer' => __('mobile/student/exams.validation.semester_id.integer'),
            'semester_id.exists' => __('mobile/student/exams.validation.semester_id.exists'),
            'exam_id.integer' => __('mobile/student/exams.validation.exam_id.integer'),
            'exam_id.exists' => __('mobile/student/exams.validation.exam_id.exists'),
            'subject_id.integer' => __('mobile/student/exams.validation.subject_id.integer'),
            'subject_id.exists' => __('mobile/student/exams.validation.subject_id.exists'),
            'teacher_id.integer' => __('mobile/student/exams.validation.teacher_id.integer'),
            'teacher_id.exists' => __('mobile/student/exams.validation.teacher_id.exists'),
            'status.in' => __('mobile/student/exams.validation.status.in'),
            'min_result.numeric' => __('mobile/student/exams.validation.min_result.numeric'),
            'min_result.min' => __('mobile/student/exams.validation.min_result.min'),
            'max_result.numeric' => __('mobile/student/exams.validation.max_result.numeric'),
            'max_result.min' => __('mobile/student/exams.validation.max_result.min'),
            'max_result.gte' => __('mobile/student/exams.validation.max_result.gte'),
            'submitted_from.date' => __('mobile/student/exams.validation.submitted_from.date'),
            'submitted_to.date' => __('mobile/student/exams.validation.submitted_to.date'),
            'submitted_to.after_or_equal' => __('mobile/student/exams.validation.submitted_to.after_or_equal'),
            'sort.in' => __('mobile/student/exams.validation.sort.in'),
        ];
    }
}
