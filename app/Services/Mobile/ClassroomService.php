<?php

namespace App\Services\Mobile;

use App\Models\Classroom;
use App\Models\Supervisor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassroomService
{
    public function classroomsWithSubjectsForSupervisorStage(): array
    {
        $supervisor = Supervisor::where('user_id', Auth::id())->first();
        if (!$supervisor) {
            throw new \RuntimeException(__('mobile/supervisor/classrooms.errors.supervisor_not_found'));
        }
        $classrooms = Classroom::with([
            'subjects' => function ($q) {
                $q->select('id', 'name', 'amount', 'classroom_id');
            }
        ])
            ->where('stage_id', $supervisor->stage_id)
            ->select('id', 'name')
            ->get();
        if ($classrooms->isEmpty()) {
            return [];
        }
        return $classrooms->map(function ($c) {
            return [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'subjects' => $c->subjects->map(function ($s) {
                    return [
                        'id' => (int) $s->id,
                        'name' => (string) $s->name,
                        'amount' => $s->amount !== null ? (int) $s->amount : null,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }
}
