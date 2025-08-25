<?php

namespace App\Http\Controllers\Api\V1\Mobile\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Teacher\CreateCallRequest;
use App\Models\Call;
use App\Services\Mobile\ZegoService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\SectionSubject;
use App\Http\Requests\Mobile\Teacher\ScheduleCallRequest;
use App\Models\ScheduledCall;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Mobile\Teacher\DeleteScheduledCallRequest;
use App\Services\Mobile\CallService;
use Illuminate\Support\Facades\Log;


class TeacherCallController extends Controller
{
    protected $zego;

    public function __construct(ZegoService $zego, private CallService $callService)
    {
        $this->zego = $zego;
    }

    public function schedule(ScheduleCallRequest $request)
    {
        $user = auth()->user();
        $teacher = $user ? $user->teacher : null;
        if (!$teacher) {
            return response()->json([
                'status' => false,
                'message' => __('mobile/teacher/call.errors.not_teacher'),
            ], 403);
        }
        $data = $request->validated();
        $sectionId = (int) $data['section_id'];
        $subjectId = (int) $data['subject_id'];
        $scheduledAt = Carbon::parse($data['scheduled_at']);
        $duration = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : 30;
        $channel = $data['channel_name'] ?? null;
        $assigned = SectionSubject::where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('teacher_id', $teacher->id)
            ->exists();
        if (!$assigned) {
            return response()->json([
                'status' => false,
                'message' => __('mobile/teacher/call.errors.section_or_subject_not_assigned'),
            ], 403);
        }
        $newStart = $scheduledAt->copy();
        $newEnd = $scheduledAt->copy()->addMinutes($duration);
        $overlap = ScheduledCall::where('created_by', $teacher->id)
            ->where('status', 'scheduled')
            ->whereRaw("scheduled_at < ?", [$newEnd])
            ->whereRaw("DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?", [$newStart])
            ->exists();
        if ($overlap) {
            return response()->json([
                'status' => false,
                'message' => __('mobile/teacher/call.errors.scheduled_overlap'),
                'errors' => ['scheduled_at' => [__('mobile/teacher/call.errors.scheduled_overlap')]],
            ], 422);
        }
        $activeOverlap = Call::where('created_by', $teacher->id)
            ->where(function ($q) use ($newStart, $newEnd) {
                $q->whereNull('ended_at')   // currently ongoing
                    ->where('started_at', '<', $newEnd);
            })
            ->exists();
        if ($activeOverlap) {
            return response()->json([
                'status' => false,
                'message' => __('mobile/teacher/call.errors.scheduled_overlap_with_active'),
                'errors' => ['scheduled_at' => [__('mobile/teacher/call.errors.scheduled_overlap_with_active')]],
            ], 422);
        }
        try {
            $scheduled = ScheduledCall::create([
                'created_by' => $teacher->id,
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'channel_name' => $channel,
                'scheduled_at' => $newStart,
                'duration_minutes' => $duration,
                'status' => 'scheduled',
            ]);

            return response()->json([
                'status' => true,
                'message' => __('mobile/teacher/call.scheduled_created'),
                'data' => [
                    'scheduled_call_id' => $scheduled->id,
                    'scheduled_at' => $scheduled->scheduled_at->toDateTimeString(),
                    'duration_minutes' => $scheduled->duration_minutes,
                ],
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Schedule call error', ['teacher_id' => $teacher->id, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => __('mobile/teacher/call.errors.save_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function startScheduled(ScheduledCall $scheduled_call)
    {
        $user = auth()->user();
        $teacher = $user ? $user->teacher : null;
        if (!$teacher) {
            return response()->json(['status' => false, 'message' => __('mobile/teacher/call.errors.not_teacher')], 403);
        }
        if ($scheduled_call->created_by !== $teacher->id) {
            return response()->json(['status' => false, 'message' => __('mobile/teacher/call.errors.not_owner_of_scheduled')], 403);
        }
        if ($scheduled_call->status !== 'scheduled') {
            return response()->json(['status' => false, 'message' => __('mobile/teacher/call.errors.invalid_scheduled_status')], 422);
        }
        $hasActive = Call::where('created_by', $teacher->id)->whereNull('ended_at')->exists();
        if ($hasActive) {
            return response()->json([
                'status' => false,
                'message' => __('mobile/teacher/call.errors.active_call_exists'),
                'errors' => ['active_call' => [__('mobile/teacher/call.errors.active_call_exists')]]
            ], 422);
        }

        if (now()->lt($scheduled_call->scheduled_at)) {
            return response()->json([
                'status' => false,
                'message' => __('mobile/teacher/call.errors.cannot_start_before_scheduled'),
                'errors' => [
                    'scheduled_call' => [
                        __('mobile/teacher/call.errors.cannot_start_before_scheduled_detail', [
                            'scheduled_at' => $scheduled_call->scheduled_at->toDateTimeString(),
                        ])
                    ],
                    'now' => now()
                ]
            ], 422);
        }
        try {
            return DB::transaction(function () use ($scheduled_call, $teacher) {
                $channel = $scheduled_call->channel_name ?? ($this->zego->generateChannelName() ?? 'call_' . uniqid());

                $call = Call::create([
                    'channel_name' => $channel,
                    'created_by' => $teacher->id,
                    'section_id' => $scheduled_call->section_id,
                    'subject_id' => $scheduled_call->subject_id,
                    'started_at' => now(),
                    'ended_at' => null,
                ]);
                $call->participants()->create([
                    'user_id' => auth()->id(),
                    'joined_at' => now(),
                ]);
                $scheduled_call->update([
                    'status' => 'started',
                    'call_id' => $call->id,
                ]);
                $token = null;
                if (isset($this->zego) && method_exists($this->zego, 'generateToken')) {
                    $token = $this->zego->generateToken(auth()->id());
                }
                return response()->json([
                    'status' => true,
                    'message' => __('mobile/teacher/call.started'),
                    'data' => [
                        'user_id' => Auth::id(),
                        'user_name' => Auth::user()->email,
                        'call_id' => $call->id,
                        'channel_name' => $call->channel_name,
                        'token' => $token,
                        'started_at' => $call->started_at->toDateTimeString(),
                    ],
                ], 201);
            });
        } catch (\Throwable $e) {
            \Log::error('Error starting scheduled call', ['scheduled_call_id' => $scheduled_call->id, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => __('mobile/teacher/call.errors.save_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function scheduledCalls()
    {
        try {
            $user = auth()->user();
            $teacher = $user ? $user->teacher : null;

            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => __('mobile/teacher/call.errors.not_teacher'),
                ], 403);
            }
            $now = Carbon::now();
            $query = ScheduledCall::query()
                ->where('created_by', $teacher->id)
                // ->where('status', 'scheduled')
                ->with(['section', 'subject'])
                ->orderBy('scheduled_at', 'asc');
            // $query->where('scheduled_at', '>=', $now);
            $scheduled = $query->get()->map(function (ScheduledCall $s) {
                return [
                    'id' => $s->id,
                    'section' => [
                        'id' => $s->section_id,
                        'name' => optional($s->section)->name,
                    ],
                    'subject' => [
                        'id' => $s->subject_id,
                        'name' => optional($s->subject)->name,
                    ],
                    'channel_name' => $s->channel_name,
                    'scheduled_at' => $s->scheduled_at ? $s->scheduled_at->toDateTimeString() : null,
                    'duration_minutes' => (int) $s->duration_minutes,
                    'status' => $s->status,
                    'call_id' => $s->call_id,
                    'created_at' => $s->created_at->toDateTimeString(),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $scheduled,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error fetching scheduled calls for teacher', [
                'teacher_id' => optional(auth()->user()->teacher)->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => __('mobile/teacher/call.errors.fetch_scheduled_failed') ?? 'Failed to fetch scheduled calls.',
            ], 500);
        }
    }

    /**
     * Teacher ends a call.
     */
    public function end(Call $call)
    {
        try {
            $user = auth()->user();
            $teacherId = $user?->teacher?->id;
            if (!$teacherId) {
                return response()->json(['status' => false, 'message' => __('mobile/teacher/call.errors.not_teacher')], 403);
            }
            if ((int) $call->created_by !== (int) $teacherId) {
                return response()->json(['status' => false, 'message' => __('mobile/teacher/call.errors.not_owner_of_call')], 403);
            }
            if ($call->ended_at !== null) {
                return response()->json(['status' => false, 'message' => __('mobile/teacher/call.errors.call_already_ended')], 422);
            }
            DB::transaction(function () use ($call) {
                $call->update(['ended_at' => now()]);
                $call->loadMissing('scheduledCall');
                if ($call->scheduledCall) {
                    $call->scheduledCall->delete();
                } else {
                    ScheduledCall::where('call_id', $call->id)->delete();
                }
            });
            return response()->json([
                'status' => true,
                'message' => __('messages.call_ended_successfully'),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error ending call', ['call_id' => $call->id ?? null, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => __('messages.unexpected_error'),
            ], 500);
        }
    }

    public function destroyScheduled(DeleteScheduledCallRequest $request, ScheduledCall $scheduled_call): JsonResponse
    {
        try {
            $this->callService->deleteScheduledByTeacher($scheduled_call);
            return response()->json([
                'message' => __('mobile/teacher/call.messages.deleted'),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Teacher delete scheduled call error', [
                'scheduled_call_id' => $scheduled_call->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => __('mobile/teacher/call.messages.server_error'),
            ], 500);
        }
    }
}
