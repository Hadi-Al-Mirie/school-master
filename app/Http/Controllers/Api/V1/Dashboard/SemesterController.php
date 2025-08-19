<?php

namespace App\Http\Controllers\Api\v1\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Semester;
use Illuminate\Support\Facades\Validator;

class SemesterController extends Controller
{
   
    public function index()
    {
        return Semester::all();
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year_id' => 'required|integer|exists:years,id',
            'name' => 'required|min:1|max:10',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $semester = Semester::create([
            'year_id' => $request->year_id,
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        return response()->json($semester, 201);
    }

}
