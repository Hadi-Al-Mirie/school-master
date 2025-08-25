<?php

namespace App\Http\Requests\Dashboard\schedule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Models\Period;

class InitializeWeeklyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $days = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];

        return [
            // TEACHER AVAILABILITIES
            'teacher_availabilities' => ['required', 'array', 'min:1'],
            'teacher_availabilities.*.teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'teacher_availabilities.*.day_of_week' => ['required', 'string', Rule::in($days)],
            'teacher_availabilities.*.period_ids' => ['required', 'array', 'min:1'],
            'teacher_availabilities.*.period_ids.*' => ['required', 'integer', 'exists:periods,id'],

            // CLASSROOMS CONFIG
            'classrooms' => ['required', 'array', 'min:1'],
            'classrooms.*.classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'classrooms.*.periods_per_day' => ['required', 'array', 'min:1'],
            // each day is optional but must be integer >= 0 when provided
            'classrooms.*.periods_per_day.saturday' => ['sometimes', 'integer', 'min:0'],
            'classrooms.*.periods_per_day.sunday' => ['sometimes', 'integer', 'min:0'],
            'classrooms.*.periods_per_day.monday' => ['sometimes', 'integer', 'min:0'],
            'classrooms.*.periods_per_day.tuesday' => ['sometimes', 'integer', 'min:0'],
            'classrooms.*.periods_per_day.wednesday' => ['sometimes', 'integer', 'min:0'],
            'classrooms.*.periods_per_day.thursday' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            // teacher_availabilities
            'teacher_availabilities.required' => __('dashboard/schedule/initialize/validation.teacher_availabilities.required'),
            'teacher_availabilities.array' => __('dashboard/schedule/initialize/validation.teacher_availabilities.array'),
            'teacher_availabilities.min' => __('dashboard/schedule/initialize/validation.teacher_availabilities.min'),

            'teacher_availabilities.*.teacher_id.required' => __('dashboard/schedule/initialize/validation.teacher_id.required'),
            'teacher_availabilities.*.teacher_id.integer' => __('dashboard/schedule/initialize/validation.teacher_id.integer'),
            'teacher_availabilities.*.teacher_id.exists' => __('dashboard/schedule/initialize/validation.teacher_id.exists'),

            'teacher_availabilities.*.day_of_week.required' => __('dashboard/schedule/initialize/validation.day_of_week.required'),
            'teacher_availabilities.*.day_of_week.string' => __('dashboard/schedule/initialize/validation.day_of_week.string'),
            'teacher_availabilities.*.day_of_week.in' => __('dashboard/schedule/initialize/validation.day_of_week.in'),

            'teacher_availabilities.*.period_ids.required' => __('dashboard/schedule/initialize/validation.period_ids.required'),
            'teacher_availabilities.*.period_ids.array' => __('dashboard/schedule/initialize/validation.period_ids.array'),
            'teacher_availabilities.*.period_ids.min' => __('dashboard/schedule/initialize/validation.period_ids.min'),
            'teacher_availabilities.*.period_ids.*.required' => __('dashboard/schedule/initialize/validation.period_ids_item.required'),
            'teacher_availabilities.*.period_ids.*.integer' => __('dashboard/schedule/initialize/validation.period_ids_item.integer'),
            'teacher_availabilities.*.period_ids.*.distinct' => __('dashboard/schedule/initialize/validation.period_ids_item.distinct'),
            'teacher_availabilities.*.period_ids.*.exists' => __('dashboard/schedule/initialize/validation.period_ids_item.exists'),

            // classrooms
            'classrooms.required' => __('dashboard/schedule/initialize/validation.classrooms.required'),
            'classrooms.array' => __('dashboard/schedule/initialize/validation.classrooms.array'),
            'classrooms.min' => __('dashboard/schedule/initialize/validation.classrooms.min'),

            'classrooms.*.classroom_id.required' => __('dashboard/schedule/initialize/validation.classroom_id.required'),
            'classrooms.*.classroom_id.integer' => __('dashboard/schedule/initialize/validation.classroom_id.integer'),
            'classrooms.*.classroom_id.exists' => __('dashboard/schedule/initialize/validation.classroom_id.exists'),

            'classrooms.*.periods_per_day.required' => __('dashboard/schedule/initialize/validation.periods_per_day.required'),
            'classrooms.*.periods_per_day.array' => __('dashboard/schedule/initialize/validation.periods_per_day.array'),
            'classrooms.*.periods_per_day.min' => __('dashboard/schedule/initialize/validation.periods_per_day.min'),

            'classrooms.*.periods_per_day.*.integer' => __('dashboard/schedule/initialize/validation.periods_per_day_day.integer'),
            'classrooms.*.periods_per_day.*.min' => __('dashboard/schedule/initialize/validation.periods_per_day_day.min'),
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($v) {
            // Max periods/day check (you already have this)
            $totalPeriods = \App\Models\Period::count();
            $classrooms = $this->input('classrooms', []);
            foreach ($classrooms as $idx => $c) {
                $ppd = $c['periods_per_day'] ?? [];
                foreach ($ppd as $day => $count) {
                    if (is_int($count) && $count > $totalPeriods) {
                        $v->errors()->add(
                            "classrooms.$idx.periods_per_day.$day",
                            __('dashboard/schedule/initialize/validation.periods_per_day_day.exceeds_max', ['max' => $totalPeriods])
                        );
                    }
                }
            }

            // Ensure no duplicate (teacher_id, day_of_week) rows in the same payload
            $tas = $this->input('teacher_availabilities', []);
            $seenTeacherDay = [];
            foreach ($tas as $i => $row) {
                $tid = $row['teacher_id'] ?? null;
                $day = $row['day_of_week'] ?? null;
                if ($tid && $day) {
                    $key = $tid . '|' . $day;
                    if (isset($seenTeacherDay[$key])) {
                        $v->errors()->add(
                            "teacher_availabilities.$i.day_of_week",
                            __('dashboard/schedule/initialize/validation.day_of_week.in')
                        );
                    }
                    $seenTeacherDay[$key] = true;
                }

                // Enforce uniqueness WITHIN EACH period_ids list only
                $pids = $row['period_ids'] ?? [];
                if (is_array($pids) && count($pids) !== count(array_unique($pids))) {
                    $v->errors()->add(
                        "teacher_availabilities.$i.period_ids",
                        __('dashboard/schedule/initialize/validation.period_ids.duplicate_within')
                    );
                }
            }
        });
    }

}