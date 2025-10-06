<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
class Section extends Model
{
    protected $fillable = ['name', 'classroom_id'];
    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }
    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function sectionSubjects()
    {
        return $this->hasMany(SectionSubject::class);
    }

    public function sectionSchedules()
    {
        return $this->hasMany(SectionSchedule::class);
    }
    public function calls()
    {
        return $this->hasMany(Call::class);
    }

    public function studentRanking(string $type = 'all', int $teacherId = null)
    {
        $students = $this->students()->with('user')->get();
        return $students
            ->map(function (\App\Models\Student $student) use ($type, $teacherId) {
                return [
                    'student' => [
                        'first_name' => optional($student->user)->first_name,
                        'last_name' => optional($student->user)->last_name,
                    ],
                    'points' => $student->calculatePoints($type, $teacherId),
                ];
            })
            ->sortByDesc('points')
            ->values();
    }

    public function averageExamResultForTeacher(int $teacherId, array $subjectIds): float
    {
        $examIds = \App\Models\Exam::query()
            ->where('section_id', $this->id)
            ->whereIn('subject_id', $subjectIds)
            ->pluck('id');
        if ($examIds->isEmpty()) {
            return 0.0;
        }
        $studentIds = $this->students()->pluck('id');
        if ($studentIds->isEmpty()) {
            return 0.0;
        }
        $avg = \App\Models\ExamAttempt::query()
            ->whereIn('exam_id', $examIds)
            ->whereIn('student_id', $studentIds)
            ->where('teacher_id', $teacherId)
            ->avg('result');
        return $avg === null ? 0.0 : (float) $avg;
    }
}