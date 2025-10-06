<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Stage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
class StageController extends Controller
{
    public function index(Request $request)
    {
        $stages = Stage::with([
            'classrooms' => function ($q) {
                $q->select('id', 'name', 'stage_id')->orderBy('name');
            },
            'classrooms.sections' => function ($q) {
                $q->select('id', 'name', 'classroom_id')->orderBy('name');
            },
        ])
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        $classroomIds = $stages
            ->pluck('classrooms')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->values();
        $rows = DB::table('sections')
            ->join('section_subjects', 'section_subjects.section_id', '=', 'sections.id')
            ->join('subjects', 'subjects.id', '=', 'section_subjects.subject_id')
            ->whereIn('sections.classroom_id', $classroomIds)
            ->distinct()
            ->select(
                'sections.classroom_id as classroom_id',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'subjects.amount as subject_amount'
            )
            ->orderBy('subject_name')
            ->get();
        $subjectsByClassroom = [];
        foreach ($rows as $r) {
            $subjectsByClassroom[$r->classroom_id][] = [
                'id' => (int) $r->subject_id,
                'name' => (string) $r->subject_name,
                'amount' => isset($r->subject_amount) ? (int) $r->subject_amount : null,
            ];
        }
        $data = $stages->map(function ($stage) use ($subjectsByClassroom) {
            $classrooms = $stage->classrooms->map(function ($classroom) use ($subjectsByClassroom) {
                return [
                    'id' => (int) $classroom->id,
                    'name' => (string) $classroom->name,
                    'subjects' => array_values($subjectsByClassroom[$classroom->id] ?? []),
                    'sections' => $classroom->sections
                        ->map(fn($section) => [
                            'id' => (int) $section->id,
                            'name' => (string) $section->name,
                        ])
                        ->values(),
                ];
            })->values();

            return [
                'id' => (int) $stage->id,
                'name' => (string) $stage->name,
                'classrooms' => $classrooms,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    public function indexStagesOnly()
    {
        try {
            $stages = Stage::all()
                ->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'name' => $s->name
                    ];
                });
            return response()->json(
                [
                    "success" => true,
                    "message" => "loaded",
                    "data" => $stages
                ],
                200
            );
        } catch (\Exception $e) {
            Log::error('stage only list error ' . $e->getMessage());
            return response()->json([
                'message' => __('list stages only failed'),
                'success' => false,
            ], 500);
        }
    }

}