<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Stage;
use App\Models\Section;
use App\Models\Classroom;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
class LoginStudentController extends Controller
{
    public function index(Request $request)
    {
        $stages = Stage::with([
            'classrooms.sections' => function ($q) {
                $q->select('id', 'name', 'classroom_id');
            },
            'classrooms' => function ($q) {
                $q->select('id', 'name', 'stage_id');
            }
        ])
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        $payload = $stages->mapWithKeys(function ($stage) {
            $classrooms = $stage->classrooms->map(function ($classroom) {
                return [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'sections' => $classroom->sections->map(function ($section) {
                        return [
                            'id' => $section->id,
                            'name' => $section->name,
                        ];
                    })->values(),
                ];
            })->values();
            $key = $stage->name ?: "stage_{$stage->id}";
            return [$key => $classrooms];
        });
        return response()->json([
            'success' => true,
            'data' => $payload,
        ], 200);

    }
}
