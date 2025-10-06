<?php

namespace App\Services\Mobile;

use App\Models\Note;
use App\Models\Semester;
use App\Models\Supervisor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
class NoteService
{
    public function supervisor(): ?Supervisor
    {
        return Supervisor::where('user_id', Auth::id())->first();
    }

    public function activeSemester(): ?Semester
    {
        return Semester::where('is_active', true)->first();
    }

    public function stageNotes(string $status = 'pending')
    {
        $supervisor = $this->supervisor();
        if (!$supervisor) {
            throw new \RuntimeException(__('mobile/supervisor/notes.errors.supervisor_not_found'));
        }
        $q = Note::query()
            ->with(['student:id,user_id,stage_id,classroom_id,section_id', 'createdBy:id,first_name,last_name'])
            ->whereHas('student', function ($s) use ($supervisor) {
                $s->where('stage_id', $supervisor->stage_id);
            })
            ->orderByDesc('id');
        if ($semester = $this->activeSemester()) {
            $q->where('semester_id', $semester->id);
        }
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        return $q->get(['id', 'by_id', 'student_id', 'semester_id', 'type', 'reason', 'status', 'value', 'created_at']);
    }

    public function decide(Note $note, string $decision, ?float $value = null): Note
    {
        $supervisor = $this->supervisor();
        if (!$supervisor) {
            throw new \RuntimeException(__('mobile/supervisor/notes.errors.supervisor_not_found'));
        }
        if ((int) $note->student->stage_id !== (int) $supervisor->stage_id) {
            throw new \RuntimeException(__('mobile/supervisor/notes.errors.note_not_in_stage'));
        }
        if ($note->status !== 'pending') {
            throw new \RuntimeException(__('mobile/supervisor/notes.errors.note_already_processed'));
        }
        if ($decision === 'approved') {
            $note->status = 'approved';
            $note_value = $note->type === 'positive' ? abs($value) : -abs($value);
            $note->value = $note_value;
        } else {
            $note->status = 'dismissed';
            $note->value = null;
        }
        $note->save();
        return $note->fresh(['student:id,stage_id,classroom_id,section_id,user_id', 'createdBy:id,first_name,last_name']);
    }
}