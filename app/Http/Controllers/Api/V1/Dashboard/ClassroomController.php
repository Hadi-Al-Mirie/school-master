<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Classroom;

class ClassroomController extends Controller
{
    public function index()
    {
        $classrooms = Classroom::query()
            ->withSum('subjects as min_required_section_subjects', 'amount')
            ->get(['id', 'name', 'stage_id', 'supervisor_id']);
        $classrooms = $classrooms->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'min_required_section_subjects' => (int) $c->min_required_section_subjects,
        ]);
        return response()->json($classrooms, 200);
    }
}