<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\schedule\InitializeWeeklyScheduleRequest;
use App\Services\Dashboard\WeeklyScheduleService;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Models\Period;

class ScheduleController extends Controller
{
    public function __construct(private WeeklyScheduleService $service)
    {
    }

    /**
     * Initialize weekly schedule:
     * - Sync teacher_availabilities
     * - Initialize section_schedules per classroom/day with empty slots
     */
    public function initializeWeekly(InitializeWeeklyScheduleRequest $request)
    {
        $payload = $request->validated();

        try {
            $result = $this->service->initialize(
                $payload['teacher_availabilities'],
                $payload['classrooms']
            );

            return response()->json([
                'success' => true,
                'message' => __('dashboard/schedule/initialize/messages.initialized_successfully'),
                'data' => $result,
            ], 201);

        } catch (Throwable $e) {
            Log::error('Initialize weekly schedule failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('dashboard/schedule/initialize/messages.unexpected_error'),
            ], 500);
        }
    }

     public function periods()
    {
        try {
            $periods = Period::orderBy('id')->get();

            return response()->json([
                'success' => true,
                'message' => __('dashboard/schedule/periods/messages.fetched_successfully'),
                'data'    => $periods,
            ], 200);

        } catch (Throwable $e) {
            Log::error('Fetch periods failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => __('dashboard/schedule/periods/messages.unexpected_error'),
            ], 500);
        }
    }
}
