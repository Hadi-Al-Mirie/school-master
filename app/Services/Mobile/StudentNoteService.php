<?php

namespace App\Services\Mobile;

use App\Models\Note;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Support\Facades\DB;

class StudentNoteService
{
    public function index(int $userId, array $filters = []): array
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