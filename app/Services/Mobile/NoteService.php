<?php

namespace App\Services\Mobile;

use App\Models\Note;
use App\Models\Semester;
use App\Models\Supervisor;
use Illuminate\Support\Facades\Auth;
use App\Models\Student;
use App\Models\Year;

use Illuminate\Support\Facades\DB;
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

    public function supervisorStore(array $data, $user): array
    {
        $supervisor = $user ? $user->supervisor : null;
        if (!$supervisor) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/supervisor/notes.errors.not_supervisor')
                ]
            ];
        }
        $status = 'approved';
        $semester = Semester::where('is_active', true)->first();
        $student = Student::find($data['student_id']);
        $studentStage = $student->classroom->stage;
        $superVisorStage = $supervisor->stage;
        if ($superVisorStage->id !== $studentStage->id) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/supervisor/notes.errors.student_not_in_stage')
                ]
            ];
        }
        $raw = (float) $data['value'];
        $value = $data['type'] === 'positive' ? abs($raw) : -abs($raw);
        $note = Note::create([
            'by_id' => $user->id,
            'student_id' => $data['student_id'],
            'reason' => $data['reason'],
            'type' => $data['type'],
            'semester_id' => $semester->id,
            'value' => $value,
            'status' => $status
        ]);
        return [
            'status' => 201,
            'body' => [
                'success' => true,
                'message' => __('mobile/supervisor/notes.created'),
                'data' => $note
            ]
        ];
    }

    public function teacherStore(array $data, $user): array
    {
        $teacherUserId = $user ? $user->id : null;
        $semester = Semester::where('is_active', true)->firstOrFail();
        $raw = (float) $data['value'];
        $value = $data['type'] === 'positive' ? abs($raw) : -abs($raw);
        $note = Note::create([
            'student_id' => $data['student_id'],
            'semester_id' => $semester->id,
            'by_id' => $teacherUserId,
            'type' => $data['type'],
            'status' => 'approved',
            'value' => $value,
            'reason' => $data['reason']
        ]);
        return [
            'status' => 201,
            'body' => [
                'success' => true,
                'message' => __('mobile/teacher/notes.created'),
                'note' => $note
            ]
        ];
    }


    public function studentNotesIndex(int $userId, array $filters = []): array
    {
        $student = Student::with('user:id,first_name,last_name')
            ->where('user_id', $userId)
            ->first();

        if (!$student) {
            return ['ok' => false, 'message' => __('mobile/student/notes.errors.student_not_found')];
        }

        $activeYear = Year::where('is_active', true)->first();
        if (!$activeYear) {
            return ['ok' => false, 'message' => __('mobile/student/notes.errors.active_year_not_found')];
        }
        $semesterIds = Semester::where('year_id', $activeYear->id)->pluck('id');
        $q = Note::query()
            ->with([
                'createdBy:id,first_name,last_name,email',
                'semester:id,name,year_id',
            ])
            ->where('student_id', $student->id)
            ->whereIn('semester_id', $semesterIds);
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $q->where('type', $filters['type']);
        }
        if (!empty($filters['semester_id']) && $semesterIds->contains((int) $filters['semester_id'])) {
            $q->where('semester_id', (int) $filters['semester_id']);
        }
        switch ($filters['sort'] ?? 'newest') {
            case 'oldest':
                $q->orderBy('created_at', 'asc');
                break;
            case 'highest_value':
                $q->orderByDesc(DB::raw('COALESCE(value,0)'));
                break;
            case 'lowest_value':
                $q->orderBy(DB::raw('COALESCE(value,0)'));
                break;
            default:
                $q->orderByDesc('created_at');
        }
        $notes = $q->get();
        $approved = Note::where('student_id', $student->id)
            ->whereIn('semester_id', $semesterIds)
            ->where('status', 'approved');
        $positivePoints = (clone $approved)->where('type', 'positive')->sum(DB::raw('COALESCE(value,0)'));
        $negativePoints = (clone $approved)->where('type', 'negative')->sum(DB::raw('COALESCE(value,0)'));
        $positiveCount = (int) (clone $approved)->where('type', 'positive')->count();
        $negativeCount = (int) (clone $approved)->where('type', 'negative')->count();
        $items = $notes->map(function (Note $n) {
            return [
                'id' => $n->id,
                'reason' => $n->reason,
                'type' => $n->type,
                'status' => $n->status,
                'value' => $n->value ?? 0,
                'semester' => $n->semester ? ['id' => $n->semester->id, 'name' => $n->semester->name] : null,
                'created_by' => $n->createdBy ? [
                    'id' => $n->createdBy->id,
                    'name' => trim(($n->createdBy->first_name ?? '') . ' ' . ($n->createdBy->last_name ?? '')),
                    'email' => $n->createdBy->email ?? null,
                ] : null,
                'created_at' => $n->created_at,
            ];
        });

        return [
            'ok' => true,
            'message' => __('mobile/student/notes.success.loaded'),
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->user ? ($student->user->first_name . ' ' . $student->user->last_name) : null,
                ],
                'year' => [
                    'id' => $activeYear->id,
                    'name' => $activeYear->name,
                ],
                'totals' => [
                    'positive' => ['count' => $positiveCount, 'points' => $positivePoints],
                    'negative' => ['count' => $negativeCount, 'points' => $negativePoints],
                    'net_points' => $positivePoints + $negativePoints,
                ],
                'notes' => $items,
                'count' => $items->count(),
            ],
        ];
    }
}