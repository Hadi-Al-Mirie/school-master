<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Events\EvetStoreRequest;
use App\Models\Event;
class EventsController extends Controller
{
    public function index()
    {
        $events = Event::orderBy('date', 'desc')->get();
        return response()->json($events);
    }

    public function store(EvetStoreRequest $request)
    {
        $events = Event::create($request->only(['start_date', 'end_date', 'notes', 'title', 'semester_id']));
        return response()->json([
            'success' => true,
            'message' => 'Events created successfully',
            'data' => $events
        ], 201);
    }
}
