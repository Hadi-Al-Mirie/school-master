<?php

namespace App\Http\Requests\Mobile\Supervisor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Classroom;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Supervisor;
use Illuminate\Support\Facades\Log;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route group already has IsSupervisor middleware
        return true;
    }

    public function rules(): array
    {
        return [
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'max_result' => ['required', 'numeric', 'min:100', 'max:600'],
            'name' => ['required', 'string', 'max:255', 'min:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'classroom_id.required' => __('mobile/supervisor/exam.validation.classroom_id.required'),
            'classroom_id.integer' => __('mobile/supervisor/exam.validation.classroom_id.integer'),
            'classroom_id.exists' => __('mobile/supervisor/exam.validation.classroom_id.exists'),

            'subject_id.required' => __('mobile/supervisor/exam.validation.subject_id.required'),
            'subject_id.integer' => __('mobile/supervisor/exam.validation.subject_id.integer'),
            'subject_id.exists' => __('mobile/supervisor/exam.validation.subject_id.exists'),

            'max_result.required' => __('mobile/supervisor/exam.validation.max_result.required'),
            'max_result.numeric' => __('mobile/supervisor/exam.validation.max_result.numeric'),
            'max_result.min' => __('mobile/supervisor/exam.validation.max_result.min'),
            'max_result.max' => __('mobile/supervisor/exam.validation.max_result.max'),

            'name.required' => __('mobile/supervisor/exam.validation.name.required'),
            'name.string' => __('mobile/supervisor/exam.validation.name.string'),
            'name.max' => __('mobile/supervisor/exam.validation.name.max'),
            'name.min' => __('mobile/supervisor/exam.validation.name.min'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $classroomId = $this->input('classroom_id');
        $subjectId = $this->input('subject_id');

        Log::info('StoreExamRequest: incoming payload (pre-validation)', [
            'user_id' => optional($this->user())->id,
            'classroom_id' => $classroomId,
            'subject_id' => $subjectId,
            'max_result' => $this->input('max_result'),
            'name' => $this->input('name'),
        ]);

        if ($this->has('classroom_id')) {
            $classroom = Classroom::select('id', 'name', 'stage_id')->find($classroomId);
            Log::info('StoreExamRequest: classroom pre-validation', [
                'input_classroom_id' => $classroomId,
                'found' => (bool) $classroom,
                'classroom' => $classroom ? [
                    'id' => $classroom->id,
                    'name' => $classroom->name ?? null,
                    'stage_id' => $classroom->stage_id,
                ] : null,
            ]);
        }

        if ($this->has('subject_id')) {
            $subject = Subject::select('id', 'name', 'classroom_id')->find($subjectId);
            Log::info('StoreExamRequest: subject pre-validation', [
                'input_subject_id' => $subjectId,
                'found' => (bool) $subject,
                'subject' => $subject ? [
                    'id' => $subject->id,
                    'name' => $subject->name ?? null,
                    'classroom_id' => $subject->classroom_id,
                ] : null,
            ]);
        }
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $semester = Semester::where('is_active', true)->first();
            if (!$semester) {
                $v->errors()->add('semester_id', __('mobile/supervisor/exam.errors.no_active_semester'));
                return;
            }
            $supervisor = Supervisor::where('user_id', $this->user()->id)->first();
            if (!$supervisor) {
                $v->errors()->add('supervisor_id', __('mobile/supervisor/exam.errors.supervisor_not_found'));
                return;
            }
            if ($this->filled('classroom_id')) {
                $classroom = Classroom::find($this->input('classroom_id'));
                if (!$classroom) {
                    $v->errors()->add('classroom_id', __('mobile/supervisor/exam.validation.classroom_id.exists'));
                    return;
                }
                if ((int) $classroom->stage_id !== (int) $supervisor->stage_id) {
                    $v->errors()->add('classroom_id', __('mobile/supervisor/exam.errors.classroom_stage_mismatch'));
                }
            }
            if ($this->filled('classroom_id') && $this->filled('subject_id')) {
                $subject = Subject::find($this->input('subject_id'));
                if (!$subject) {
                    $v->errors()->add('subject_id', __('mobile/supervisor/exam.validation.subject_id.exists'));
                    return;
                }
                if ((int) $subject->classroom_id !== (int) $this->input('classroom_id')) {
                    $v->errors()->add('subject_id', __('mobile/supervisor/exam.errors.subject_not_in_classroom'));
                }
            }
        });
    }
}
