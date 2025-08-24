<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'father_name',
        'mother_name',
        'father_number',
        'mother_number',
        'cashed_points',
        'gender',
        'location',
        'birth_day',
        'diseases',
        'special_notes',
        'section_id',
        'classroom_id',
        'stage_id',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function dictations()
    {
        return $this->hasMany(Dictation::class);
    }
    public function notes()
    {
        return $this->hasMany(Note::class);
    }
    public function examAttempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }
    public function quizAttempts()
    {
        return $this->hasMany(\App\Models\QuizAttempt::class);
    }
    public function attendances()
    {
        return $this->morphMany(Attendance::class, 'attendable');
    }

    /**
     * Calculate this student’s points.
     *
     * @param  string     $type      "exams", "quiz", "notes" , "attendances" or "all"
     * @param  int|null   $teacherId optional teacher to filter by
     * @return float
     */
    public function calculatePoints(string $type = 'all', int $teacherId = null): float
    {
        $total = 0.00;
        if ($type === 'all' || $type === 'exams') {
            $q = $this->hasMany(\App\Models\ExamAttempt::class);
            if ($teacherId) {
                $q = $q->where('teacher_id', $teacherId);
            }
            $total += (float) $q->sum('result');
        }
        if ($type === 'all' || $type === 'quiz') {
            $q = $this->hasMany(\App\Models\QuizAttempt::class);
            if ($teacherId) {
                $q = $q->whereHas('quiz', function ($qu) use ($teacherId) {
                    $qu->where('teacher_id', $teacherId);
                });
            }
            $total += (float) $q->sum('total_score');
        }
        if ($type === 'all' || $type === 'notes') {
            $notes = $this->hasMany(\App\Models\Note::class)
                ->when($teacherId, fn($q) => $q->where('by_id', $teacherId))
                ->get();
            foreach ($notes as $note) {
                $total += (float) ($note->value ?? 0);
            }
        }
        if ($type === 'all' || $type === 'attendances') {
            $query = $this->attendances()
                ->when($teacherId, fn($q) => $q->where('by_id', $teacherId));
            $attendances = $query->with('attendanceType')->get();
            foreach ($attendances as $attendance) {
                if ($attendance->attendanceType) {
                    $total += (float) $attendance->attendanceType->value;
                }
            }
        }
        return $total;
    }
}
