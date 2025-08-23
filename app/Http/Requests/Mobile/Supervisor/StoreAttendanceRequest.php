<?php

namespace App\Http\Requests\Mobile\Supervisor;

use App\Models\Attendance;
use App\Models\Semester;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    protected function prepareForValidation(): void
    {
        $type = $this->input('type');

        // If client sent attendable_type as 'student'/'teacher', treat it like 'type'
        if (!$type && $this->filled('attendable_type') && in_array(strtolower($this->input('attendable_type')), ['student', 'teacher'])) {
            $type = strtolower($this->input('attendable_type'));
        }

        // If client sent numeric type_id, map it => student|teacher (assumption documented)
        if (!$type && $this->filled('type_id')) {
            $map = [1 => 'student', 2 => 'teacher'];
            $type = $map[(int) $this->input('type_id')] ?? null;
        }

        if ($type) {
            $this->merge(['type' => strtolower($type)]);
        }
    }

    public function rules(): array
    {
        return [
            'attendable_id' => ['required', 'integer', 'min:1'],
            'attendance_type_id' => ['required', 'integer', 'exists:attendance_types,id'],
            'att_date' => ['required', 'date_format:Y-m-d'],
            'justification' => [
                'nullable',
                'required_if:attendance_type_id,2,3',
                'string',
                'min:10',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => __('mobile/supervisor/attendance.validation.type.required'),
            'type.in' => __('mobile/supervisor/attendance.validation.type.in'),

            'attendable_id.required' => __('mobile/supervisor/attendance.validation.attendable_id.required'),
            'attendable_id.integer' => __('mobile/supervisor/attendance.validation.attendable_id.integer'),
            'attendable_id.min' => __('mobile/supervisor/attendance.validation.attendable_id.min'),

            'attendance_type_id.required' => __('mobile/supervisor/attendance.validation.attendance_type_id.required'),
            'attendance_type_id.integer' => __('mobile/supervisor/attendance.validation.attendance_type_id.integer'),
            'attendance_type_id.exists' => __('mobile/supervisor/attendance.validation.attendance_type_id.exists'),

            'att_date.required' => __('mobile/supervisor/attendance.validation.att_date.required'),
            'att_date.date_format' => __('mobile/supervisor/attendance.validation.att_date.date_format'),

            'justification.required_if' => __('mobile/supervisor/attendance.validation.justification.required_if'),
            'justification.string' => __('mobile/supervisor/attendance.validation.justification.string'),
            'justification.min' => __('mobile/supervisor/attendance.validation.justification.min'),
            'justification.max' => __('mobile/supervisor/attendance.validation.justification.max'),
        ];
    }

    /**
     * Cross-field and DB-aware validations:
     * - attendable_id exists in the correct table (students/teachers)
     * - there is an active semester
     * - att_date lies inside the active semester range
     * - not already recorded for (attendable_type, attendable_id, att_date)
     */
    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $type = $this->input('type');
            $attendableId = (int) $this->input('attendable_id');

            // 1) Ensure attendable exists in the right table
            if ($type === 'student') {
                if (!\DB::table('students')->where('id', $attendableId)->exists()) {
                    $v->errors()->add('attendable_id', __('mobile/supervisor/attendance.validation.attendable_id.not_student'));
                }
            } elseif ($type === 'teacher') {
                if (!\DB::table('teachers')->where('id', $attendableId)->exists()) {
                    $v->errors()->add('attendable_id', __('mobile/supervisor/attendance.validation.attendable_id.not_teacher'));
                }
            }

            // 2) Active semester must exist
            $semester = Semester::where('is_active', true)->first();
            if (!$semester) {
                $v->errors()->add('semester_id', __('mobile/supervisor/attendance.errors.no_active_semester'));
                return; // other checks depend on semester
            }

            // 3) Date inside active semester window
            if ($this->filled('att_date')) {
                try {
                    $attDate = Carbon::createFromFormat('Y-m-d', $this->input('att_date'));
                } catch (\Throwable $e) {
                    // base rules will already flag bad format; stop here
                    return;
                }

                // Normalize semester bounds whether they're strings or Carbon
                $start = $semester->start_date instanceof \DateTimeInterface
                    ? Carbon::instance($semester->start_date)->startOfDay()
                    : Carbon::parse($semester->start_date)->startOfDay();

                $end = $semester->end_date instanceof \DateTimeInterface
                    ? Carbon::instance($semester->end_date)->endOfDay()
                    : Carbon::parse($semester->end_date)->endOfDay();

                if ($attDate->lt($start) || $attDate->gt($end)) {
                    $v->errors()->add('att_date', __('mobile/supervisor/attendance.validation.att_date.outside_semester'));
                }
            }

            // 4) Prevent duplicate attendance for same person+date
            if ($this->filled('att_date') && in_array($type, ['student', 'teacher'], true)) {
                $fqcn = $type === 'student' ? \App\Models\Student::class : \App\Models\Teacher::class;

                $exists = Attendance::where('attendable_type', $fqcn)
                    ->where('attendable_id', $attendableId)
                    ->whereDate('att_date', $this->input('att_date'))
                    ->exists();

                if ($exists) {
                    $v->errors()->add('att_date', __('mobile/supervisor/attendance.errors.duplicate'));
                }
            }
            if ((int) $this->input('attendance_type_id') === 1) {
                $j = $this->input('justification', null);
                if (!is_null($j) && trim((string) $j) !== '') {
                    $v->errors()->add('justification', __('mobile/supervisor/attendance.validation.justification.must_be_null'));
                }
            }
        });
    }
}