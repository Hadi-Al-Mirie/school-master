<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Year;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
class YearController extends Controller
{

    public function index()
    {
        $years=Year::all()->select(['id','name','start_date','end_date']);
        return response()->json(['succes'=>true,'date'=>$years],200);
    }

    public function store(Request $request)
    {
        Log::info(
            'trying to store a year',
            ['request' => $request->all()]
        );
        $validator = $request->validate([
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $semester = Year::create([
            'name' => $validator['name'],
            'start_date' => $validator['start_date'],
            'end_date' => $validator['end_date'],
        ]);

        return response()->json($semester, 201);
    }

}
