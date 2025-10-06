<?php

namespace App\Http\Requests\Mobile\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class CreateQuizRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'min:5', 'max:255'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question' => ['required', 'string', 'min:5', 'max:255'],
            'questions.*.mark' => ['required', 'numeric', 'min:0'],
            'questions.*.answers' => ['required', 'array', 'min:2'],
            'questions.*.answers.*.answer' => ['required', 'string', 'min:1', 'max:120'],
            'questions.*.answers.*.is_correct' => ['required', 'boolean'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => __('mobile/teacher/quiz.validation.name_required'),
            'name.string' => __('mobile/teacher/quiz.validation.name_string'),
            'name.min' => __('mobile/teacher/quiz.validation.name_min'),
            'name.max' => __('mobile/teacher/quiz.validation.name_max'),
            'subject_id.required' => __('mobile/teacher/quiz.validation.subject_required'),
            'subject_id.integer' => __('mobile/teacher/quiz.validation.subject_integer'),
            'subject_id.exists' => __('mobile/teacher/quiz.validation.subject_exists'),
            'classroom_id.required' => __('mobile/teacher/quiz.validation.classroom_required'),
            'classroom_id.integer' => __('mobile/teacher/quiz.validation.classroom_integer'),
            'classroom_id.exists' => __('mobile/teacher/quiz.validation.classroom_exists'),
            'section_id.required' => __('mobile/teacher/quiz.validation.section_required'),
            'section_id.integer' => __('mobile/teacher/quiz.validation.section_integer'),
            'section_id.exists' => __('mobile/teacher/quiz.validation.section_exists'),
            'start_time.required' => __('mobile/teacher/quiz.validation.start_required'),
            'start_time.date' => __('mobile/teacher/quiz.validation.start_date'),
            'end_time.required' => __('mobile/teacher/quiz.validation.end_required'),
            'end_time.date' => __('mobile/teacher/quiz.validation.end_date'),
            'end_time.after' => __('mobile/teacher/quiz.validation.end_after_start'),
            'questions.required' => __('mobile/teacher/quiz.validation.questions_required'),
            'questions.array' => __('mobile/teacher/quiz.validation.questions_array'),
            'questions.min' => __('mobile/teacher/quiz.validation.questions_min'),
            'questions.*.question.required' => __('mobile/teacher/quiz.validation.question_required'),
            'questions.*.question.string' => __('mobile/teacher/quiz.validation.question_string'),
            'questions.*.question.min' => __('mobile/teacher/quiz.validation.question_min'),
            'questions.*.question.max' => __('mobile/teacher/quiz.validation.question_max'),
            'questions.*.mark.required' => __('mobile/teacher/quiz.validation.question_mark_required'),
            'questions.*.mark.numeric' => __('mobile/teacher/quiz.validation.question_mark_numeric'),
            'questions.*.mark.min' => __('mobile/teacher/quiz.validation.question_mark_min'),
            'questions.*.answers.required' => __('mobile/teacher/quiz.validation.answers_required'),
            'questions.*.answers.array' => __('mobile/teacher/quiz.validation.answers_array'),
            'questions.*.answers.min' => __('mobile/teacher/quiz.validation.min_answers'),
            'questions.*.answers.*.answer.required' => __('mobile/teacher/quiz.validation.answer_required'),
            'questions.*.answers.*.answer.string' => __('mobile/teacher/quiz.validation.answer_string'),
            'questions.*.answers.*.answer.min' => __('mobile/teacher/quiz.validation.answer_min'),
            'questions.*.answers.*.answer.max' => __('mobile/teacher/quiz.validation.answer_max'),
            'questions.*.answers.*.is_correct.required' => __('mobile/teacher/quiz.validation.is_correct_required'),
            'questions.*.answers.*.is_correct.boolean' => __('mobile/teacher/quiz.validation.is_correct_boolean'),
        ];
    }
}