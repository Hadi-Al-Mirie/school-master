<?php

namespace App\Services\Mobile;

use App\Models\Classroom;
use App\Models\Supervisor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassroomService
{
    /**
     * Get all classrooms in the supervisor's stage, and for each classroom
     * the DISTINCT subjects that appear in any of its sections
     * (via section_subjects pivot).
     *
     * @return array<int, array{id:int, name:string, subjects: array<int, array{id:int, name:string, amount:int|null}>}>
     */
    public function classroomsWithSubjectsForSupervisorStage(): array
    {
        $supervisor = Supervisor::where('user_id', Auth::id())->first();
        if (!$supervisor) {
            throw new \RuntimeException(__('mobile/supervisor/classrooms.errors.supervisor_not_found'));
        }
        $classrooms = Classroom::where('stage_id', $supervisor->stage_id)
            ->select('id', 'name')
            ->get();
        if ($classrooms->isEmpty()) {
            return [];
        }
        $classroomIds = $classrooms->pluck('id')->all();
        $rows = DB::table('sections')
            ->join('section_subjects', 'section_subjects.section_id', '=', 'sections.id')
            ->join('subjects', 'subjects.id', '=', 'section_subjects.subject_id')
            ->whereIn('sections.classroom_id', $classroomIds)
            ->distinct()
            ->select([
                'sections.classroom_id',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'subjects.amount as subject_amount',
            ])->get();

        // Group rows by classroom_id
        $byClassroom = [];
        foreach ($rows as $r) {
            $byClassroom[$r->classroom_id][] = [
                'id' => (int) $r->subject_id,
                'name' => (string) $r->subject_name,
                'amount' => $r->subject_amount !== null ? (int) $r->subject_amount : null,
            ];
        }

        // 3) Build response array, empty subjects if none found
        $result = [];
        foreach ($classrooms as $c) {
            $subjects = $byClassroom[$c->id] ?? [];
            $result[] = [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'subjects' => $subjects,
            ];
        }

        return $result;
    }
}
