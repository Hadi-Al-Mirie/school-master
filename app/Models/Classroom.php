<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = ['name', 'stage_id', 'supervisor_id'];
    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }
    public function students()
    {
        return $this->hasMany(Student::class);
    }
    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }

    public function minRequiredSectionSubjects(): int
    {
        return (int) $this->subjects()->sum('amount');
    }
}