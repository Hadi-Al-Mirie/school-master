<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    protected $fillable = [
        'channel_name',
        'created_by',
        'started_at',
        'ended_at',
        'section_id',
        'subject_id'
    ];

    protected $dates = ['started_at', 'ended_at'];

    public function creator(): BelongsTo
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
    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class);
    }
    public function scheduledCall()
    {
        return $this->hasOne(ScheduledCall::class, 'call_id', 'id');
    }
}