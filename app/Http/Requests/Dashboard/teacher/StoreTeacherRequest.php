<?php

namespace App\Http\Requests\Dashboard\teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Models\Section;
use App\Models\Subject;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:100'],
            'last_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'min:5', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:20', 'unique:teachers,phone'],
            'section_subjects' => ['nullable', 'array', 'min:1'],
            'section_subjects.*.section_id' => ['required_with:section_subjects', 'integer', 'exists:sections,id'],
            'section_subjects.*.subject_id' => ['required_with:section_subjects', 'integer', 'exists:subjects,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => __('dashboard/teacher/store/validation.first_name.required'),
            'first_name.string' => __('dashboard/teacher/store/validation.first_name.string'),
            'first_name.min' => __('dashboard/teacher/store/validation.first_name.min'),
            'first_name.max' => __('dashboard/teacher/store/validation.first_name.max'),

            'last_name.required' => __('dashboard/teacher/store/validation.last_name.required'),
            'last_name.string' => __('dashboard/teacher/store/validation.last_name.string'),
            'last_name.min' => __('dashboard/teacher/store/validation.last_name.min'),
            'last_name.max' => __('dashboard/teacher/store/validation.last_name.max'),

            'email.required' => __('dashboard/teacher/store/validation.email.required'),
            'email.email' => __('dashboard/teacher/store/validation.email.email'),
            'email.min' => __('dashboard/teacher/store/validation.email.min'),
            'email.max' => __('dashboard/teacher/store/validation.email.max'),
            'email.unique' => __('dashboard/teacher/store/validation.email.unique'),

            'password.required' => __('dashboard/teacher/store/validation.password.required'),
            'password.string' => __('dashboard/teacher/store/validation.password.string'),
            'password.min' => __('dashboard/teacher/store/validation.password.min'),
            'password.max' => __('dashboard/teacher/store/validation.password.max'),

            'phone.required' => __('dashboard/teacher/store/validation.phone.required'),
            'phone.string' => __('dashboard/teacher/store/validation.phone.string'),
            'phone.min' => __('dashboard/teacher/store/validation.phone.min'),
            'phone.max' => __('dashboard/teacher/store/validation.phone.max'),
            'phone.unique' => __('dashboard/teacher/store/validation.phone.unique'),

            'section_subjects.array' => __('dashboard/teacher/store/validation.section_subjects.array'),
            'section_subjects.min' => __('dashboard/teacher/store/validation.section_subjects.min'),
            'section_subjects.*.section_id.required_with' => __('dashboard/teacher/store/validation.section_subjects.section_id.required'),
            'section_subjects.*.section_id.integer' => __('dashboard/teacher/store/validation.section_subjects.section_id.integer'),
            'section_subjects.*.section_id.exists' => __('dashboard/teacher/store/validation.section_subjects.section_id.exists'),
            'section_subjects.*.subject_id.required_with' => __('dashboard/teacher/store/validation.section_subjects.subject_id.required'),
            'section_subjects.*.subject_id.integer' => __('dashboard/teacher/store/validation.section_subjects.subject_id.integer'),
            'section_subjects.*.subject_id.exists' => __('dashboard/teacher/store/validation.section_subjects.subject_id.exists'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $pairs = $this->input('section_subjects', []);
            if (!is_array($pairs) || empty($pairs))
                return;
            $seen = [];
            foreach ($pairs as $idx => $pair) {
                if (!isset($pair['section_id'], $pair['subject_id']))
                    continue;
                $key = $pair['section_id'] . '-' . $pair['subject_id'];
                if (isset($seen[$key])) {
                    $v->errors()->add("section_subjects.$idx", __('dashboard/teacher/store/validation.section_subjects.duplicate'));
                }
                $seen[$key] = true;
                $section = Section::select('id', 'classroom_id')->find($pair['section_id']);
                $subject = Subject::select('id', 'classroom_id')->find($pair['subject_id']);
                if ($section && $subject && $section->classroom_id !== $subject->classroom_id) {
                    $v->errors()->add("section_subjects.$idx.subject_id", __('dashboard/teacher/store/validation.section_subjects.mismatched_classroom'));
                }
            }
        });
    }
}
