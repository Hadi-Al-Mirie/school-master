<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class Teacher extends Model
{
    protected $fillable = ['user_id', 'phone'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function sectionSubjects()
    {
        return $this->hasMany(SectionSubject::class);
    }

    public function subjects()
    {
        return $this->hasManyThrough(
            Subject::class,
            SectionSubject::class,
            'teacher_id',
            'id',
            'id',
            'subject_id'
        );
    }
    public function availabilities()
    {
        return $this->hasMany(TeacherAvailabilities::class, 'teacher_id');
    }
    /**
     * Get all Sections this teacher actually teaches (unique).
     *
     * @return \Illuminate\Support\Collection
     */
    public function sectionsTaught()
    {
        return \App\Models\Section::whereIn('id', function ($q) {
            $q->select('section_id')
                ->from('section_subjects')
                ->where('teacher_id', $this->id);
        })->get();
    }

    public function teacherPopularities()
    {
        return $this->hasMany(TeacherPopularity::class);
    }

    public function minRequiredAvailabilities(): int
    {
        return (int) DB::table('section_subjects')
            ->join('subjects', 'subjects.id', '=', 'section_subjects.subject_id')
            ->where('section_subjects.teacher_id', $this->id)
            ->sum('subjects.amount');
    }
}
