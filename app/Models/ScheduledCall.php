<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledCall extends Model
{
    protected $table = 'scheduled_calls';

    protected $fillable = [
        'created_by',
        'section_id',
        'subject_id',
        'channel_name',
        'scheduled_at',
        'duration_minutes',
        'status',
        'call_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'created_by');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function getScheduledEndAttribute()
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes);
    }
}
