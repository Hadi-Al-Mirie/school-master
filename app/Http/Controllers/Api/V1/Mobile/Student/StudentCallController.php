<?php

namespace App\Http\Controllers\Api\V1\Mobile\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Student\JoinCallRequest;
use App\Models\Call;
use App\Models\CallParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Mobile\ZegoService;
use App\Models\ScheduledCall;
use Illuminate\Http\Request;
class StudentCallController extends Controller
{
    protected $zego;

    public function __construct(ZegoService $zego)
    {
        $this->zego = $zego;
    }
    public function join(JoinCallRequest $request): JsonResponse
    {
        $user = Auth::user();
        $student = $user ? $user->student : null;

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/call.errors.not_student'),
            ], 403);
        }
        Log::info('call id', [$request->call_id]);

        $call = Call::find($request->call_id);


        if (!$call) {
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/call.validation.call_exists'),
            ], 404);
        }

        // active check
        if (is_null($call->started_at) || !is_null($call->ended_at)) {
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/call.errors.call_not_active'),
            ], 422);
        }

        // ensure student belongs to the call's section
        if ((int) $student->section_id !== (int) $call->section_id) {
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/call.errors.not_in_section'),
            ], 403);
        }

        // Determine participant user id (adjust if your participants store student_id instead)
        $participantUserId = $student->user_id ?? $student->id;

        try {
            // Check existing participant
            $existing = $call->participants()->where('user_id', $participantUserId)->first();

            if ($existing) {
                // The student was participant before and left
                if (!is_null($existing->left_at)) {
                    return response()->json([
                        'success' => false,
                        'message' => __('mobile/student/call.errors.cannot_rejoin_after_left'),
                        'data' => [
                            'left_at' => $existing->left_at ? $existing->left_at->toDateTimeString() : null,
                        ],
                    ], 403);
                }

                // Participant exists and is still in the call
                return response()->json([
                    'success' => true,
                    'message' => __('mobile/student/call.errors.already_in_call'),
                    'data' => [
                        'call_id' => $call->id,
                        'channel_name' => $call->channel_name,
                    ],
                ], 200);
            }
            return DB::transaction(function () use ($call, $participantUserId) {
                $call->participants()->create([
                    'user_id' => $participantUserId,
                    'joined_at' => now(),
                    'left_at' => null,
                ]);
                $token = null;
                if (isset($this->zego) && method_exists($this->zego, 'generateToken')) {
                    $token = $this->zego->generateToken($participantUserId);
                }
                return response()->json([
                    'success' => true,
                    'message' => __('mobile/student/call.joined'),
                    'data' => [
                        'user_id' => Auth::id(),
                        'user_name' => Auth::user()->email,
                        'call_id' => $call->id,
                        'channel_name' => $call->channel_name,
                        'token' => $token,
                        'started_at' => $call->started_at ? $call->started_at : null,
                    ],
                ], 200);
            });
        } catch (\Throwable $e) {
            Log::error('Student join call error', [
                'student_id' => $student->id ?? null,
                'call_id' => $call->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/call.errors.join_failed'),
            ], 500);
        }
    }


    public function scheduledCalls()
    {
        try {
            $user = Auth::user();
            $student = $user ? $user->student : null;

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => __('mobile/student/call.errors.not_student'),
                ], 403);
            }

            $sectionId = $student->section_id;
            if (!$sectionId) {
                return response()->json([
                    'success' => false,
                    'message' => __('mobile/student/call.errors.no_section'),
                ], 422);
            }
            $now = now();
            $query = ScheduledCall::query()
                ->where('section_id', $sectionId)
                // ->where('status', 'scheduled')
                ->with(['subject', 'teacher'])
                ->orderBy('scheduled_at', 'asc');
            // $query->where('scheduled_at', '>=', $now);
            $scheduled = $query->get()->map(function (ScheduledCall $s) {
                return [
                    'id' => $s->id,
                    'subject' => [
                        'id' => $s->subject_id,
                        'name' => optional($s->subject)->name,
                    ],
                    'teacher' => [
                        'id' => $s->created_by,
                        'name' => optional($s->teacher)->name ?? optional($s->teacher->user)->name ?? null,
                    ],
                    'channel_name' => $s->channel_name,
                    'call_id' => $s->call->id ?? null,
                    'scheduled_at' => $s->scheduled_at ? $s->scheduled_at->toDateTimeString() : null,
                    'duration_minutes' => (int) $s->duration_minutes,
                    'status' => $s->status,
                ];
            })->values();
            return response()->json([
                'success' => true,
                'data' => $scheduled,
            ], 200);
        } catch (\Throwable $e) {
            \Log::error('Failed fetching scheduled calls for student', [
                'student_id' => optional($student)->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => __('mobile/student/call.errors.fetch_failed'),
            ], 500);
        }
    }
}
