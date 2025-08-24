<?php

namespace App\Http\Controllers\Api\V1\Mobile\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Supervisor\ListStageNotesRequest;
use App\Http\Requests\Mobile\Supervisor\DecideNoteRequest;
use App\Models\Note;
use App\Services\Mobile\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SupervisorNoteApprovalController extends Controller
{
    public function __construct(private NoteService $noteService)
    {
        // auth:sanctum + IsSupervisor on route group
    }

    /**
     * GET: Notes in the supervisor's stage (default: pending, active semester).
     */
    public function index(ListStageNotesRequest $request): JsonResponse
    {
        try {
            $notes = $this->noteService->stageNotes(
                status: $request->input('status', 'pending')
            );

            return response()->json([
                'message' => __('mobile/supervisor/notes.messages.loaded'),
                'notes' => $notes,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Database\QueryException $e) {
        Log::error('Supervisor list stage notes query error', ['error' => $e->getMessage()]);
        return response()->json(['message' => __('mobile/supervisor/notes.messages.server_error')], 500);
        } catch (\Throwable $e) {
            Log::error('Supervisor list stage notes error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => __('mobile/supervisor/notes.messages.server_error'),
            ], 500);
        }
    }

    /**
     * POST: Decide a note (approve with value OR dismiss).
     * Route model binding gives us the Note instance.
     */
    public function decide(Note $note, DecideNoteRequest $request): JsonResponse
    {
        try {
            $note->loadMissing('student:id,stage_id');
            $updated = $this->noteService->decide(
                note: $note,
                decision: $request->input('decision'),
                value: $request->input('value')
            );

            return response()->json([
                'message' => $request->input('decision') === 'approved'
                    ? __('mobile/supervisor/notes.messages.approved')
                    : __('mobile/supervisor/notes.messages.dismissed'),
                'note' => [
                    'id' => $updated->id,
                    'status' => $updated->status,
                    'value' => $updated->value,
                    'student_id' => $updated->student_id,
                    'type' => $updated->type,
                    'reason' => $updated->reason,
                    'semester_id' => $updated->semester_id,
                    'by_id' => $updated->by_id,
                    'updated_at' => $updated->updated_at,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Supervisor decide note error', [
                'note_id' => $note->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => __('mobile/supervisor/notes.messages.server_error'),
            ], 500);
        }
    }
}
