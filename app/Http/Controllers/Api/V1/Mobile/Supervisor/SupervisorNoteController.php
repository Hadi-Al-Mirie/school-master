<?php

namespace App\Http\Controllers\Api\V1\Mobile\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Supervisor\CreateNoteRequest;
use App\Models\Note;
use App\Models\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Student;
class SupervisorNoteController extends Controller
{
    /**
     * Supervisor creates a note for a student.
     */
    public function store(CreateNoteRequest $request): JsonResponse
    {
        $user = Auth::user();
        $supervisor = $user ? $user->supervisor : null;

        if (!$supervisor) {
            return response()->json([
                'success' => false,
                'message' => __('mobile/supervisor/notes.errors.not_supervisor'),
            ], 403);
        }

        $data = $request->validated();
        $status = 'approved';
        $semester = Semester::where('is_active', true)->first();
        $student = Student::find($data['student_id']);
        $studentStage = $student->classroom->stage;
        $superVisorStage = $supervisor->stage;
        if ($superVisorStage->id !== $studentStage->id) {
            return response()->json([
                'success' => false,
                'message' => __('mobile/supervisor/notes.errors.student_not_in_stage'),
            ], 403);
        }
        try {
            $note = Note::create([
                'by_id' => $user->id,
                'student_id' => $data['student_id'],
                'reason' => $data['reason'],
                'type' => $data['type'],
                'semester_id' => $semester->id,
                'value' => $data['value'] ?? null,
                'status' => $status,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('mobile/supervisor/notes.created'),
                'data' => $note,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Supervisor create note failed', [
                'supervisor_id' => $supervisor->id ?? null,
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('mobile/supervisor/notes.errors.save_failed'),
            ], 500);
        }
    }
}