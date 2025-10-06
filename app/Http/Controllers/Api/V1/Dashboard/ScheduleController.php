<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\schedule\InitializeWeeklyScheduleRequest;
use App\Services\Dashboard\Schedule\ScheduleGeneratorService;
use App\Services\Dashboard\Schedule\InitWeeklyScheduleService;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Models\Period;

class ScheduleController extends Controller
{
    protected $scheduleGenerator;
    public function __construct(private InitWeeklyScheduleService $service, ScheduleGeneratorService $scheduleGenerator)
    {
        $this->scheduleGenerator = $scheduleGenerator;
    }
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


    public function generate()
    {
        $options = [
            'get_all_schedules' => false,
            'optimize' => true,
            'force_assign' => false,
        ];

        try {
            $result = $this->scheduleGenerator->generate($options);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'] ?? 'Schedule generated',
                'data' => $result['data'] ?? null,
                'schedule' => $result['schedule'] ?? null,
                'schedules' => $result['schedules'] ?? null,
                'total_count' => $result['total_count'] ?? null,
                'suggestions' => $result['suggestions'] ?? null,
            ], $result['success'] ? 200 : 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reset()
    {
        try {
            $this->scheduleGenerator->reset();

            return response()->json([
                'success' => true,
                'message' => 'All schedules have been reset'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset schedules: ' . $e->getMessage()
            ], 500);
        }
    }
    public function status()
    {
        try {
            $status = $this->scheduleGenerator->getStatus();

            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status: ' . $e->getMessage()
            ], 500);
        }
    }
    public function export()
    {
        try {
            $data = $this->scheduleGenerator->export();
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export schedule: ' . $e->getMessage()
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
                'data' => $periods,
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
