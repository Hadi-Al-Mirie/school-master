<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SectionSubject extends Model
{
    protected $fillable = [
        'section_id',
        'subject_id',
        'teacher_id'
    ];
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
    public function teachers()
    {
        return $this->belongsToMany(
            Teacher::class,
            'section_subjects',
            'subject_id',
            'teacher_id'
        )->withTimestamps();
    }
}