<?php

namespace App\Http\Controllers\Api\v1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Year;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class YearController extends Controller
{
   
    public function index()
    {
        return Year::all();
    }

    public function store(Request $request)
    {
        $validator =$request->validate([
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $semester = Year::create([
            'name' => $validator['name'],
            'start_date' =>  $validator['start_date'],
            'end_date' =>  $validator['end_date'],
        ]);

        return response()->json($semester, 201);
    }

}

