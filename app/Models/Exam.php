<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $table = 'exams';

    protected $fillable = [
        'created_by',
        'section_id',
        'subject_id',
        'semester_id',
        'name',
        'status',
        'max_result',
    ];

    protected $casts = [
        'max_result' => 'float',
    ];

    public const STATUS_WAIT = 'wait';
    public const STATUS_RELEASED = 'released';

    // Relations
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class, 'created_by');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
