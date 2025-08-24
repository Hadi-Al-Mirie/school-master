<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use Illuminate\Support\Facades\Validator;
class EventsController extends Controller
{
    public function index()
    {
        $events = Event::orderBy('date', 'desc')->get();
        return response()->json($events);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'notes' => 'required|string|min:2|max:255',
            'title' => 'required|string|max:255',
            'semester_id' => 'required|integer|exists:semesters,id',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $events = Event::create($request->only(['start_date', 'end_date', 'notes', 'title']));

        return response()->json([
            'success' => true,
            'message' => 'Events created successfully',
            'data' => $events
        ], 201);
    }

}
