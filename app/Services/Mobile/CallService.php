<?php
namespace App\Services\Mobile;
use App\Models\ScheduledCall;
use App\Models\Call;
use App\Models\SectionSubject;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CallService
{
    public function __construct(private ZegoService $zego)
    {
    }
    public function studentJoin(array $data, $user): array
    {
        $student = $user ? $user->student : null;
        if (!$student) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/student/call.errors.not_student')
                ]
            ];
        }
        $call = Call::find($data['call_id']);
        if (!$call) {
            return [
                'status' => 404,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/student/call.validation.call_exists')
                ]
            ];
        }
        if (is_null($call->started_at) || !is_null($call->ended_at)) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/student/call.errors.call_not_active')
                ]
            ];
        }
        if ((int) $student->section_id !== (int) $call->section_id) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/student/call.errors.not_in_section')
                ]
            ];
        }
        $participantUserId = $student->user_id;
        $existing = $call->participants()->where('user_id', $participantUserId)->first();
        if ($existing) {
            if (!is_null($existing->left_at)) {
                return [
                    'status' => 403,
                    'body' => [
                        'success' => false,
                        'message' => __('mobile/student/call.errors.cannot_rejoin_after_left'),
                        'data' => [
                            'left_at' => $existing->left_at ? $existing->left_at->toDateTimeString() : null
                        ]
                    ]
                ];
            }
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => __('mobile/student/call.errors.already_in_call'),
                    'data' => [
                        'call_id' => $call->id,
                        'channel_name' => $call->channel_name
                    ]
                ]
            ];
        }
        $result = DB::transaction(function () use ($call, $participantUserId, $user) {
            $call->participants()->create([
                'user_id' => $participantUserId,
                'joined_at' => now(),
                'left_at' => null
            ]);
            $token = null;
            if (isset($this->zego) && method_exists($this->zego, 'generateToken')) {
                $token = $this->zego->generateToken($participantUserId);
            }
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => __('mobile/student/call.joined'),
                    'data' => [
                        'user_id' => $participantUserId,
                        'user_name' => $user?->email,
                        'call_id' => $call->id,
                        'channel_name' => $call->channel_name,
                        'token' => $token,
                        'started_at' => $call->started_at ? $call->started_at : null
                    ]
                ]
            ];
        });
        return $result;
    }
    public function studentScheduledCalls($user): array
    {
        $student = $user ? $user->student : null;
        if (!$student) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/student/call.errors.not_student')
                ]
            ];
        }
        $sectionId = $student->section_id;
        if (!$sectionId) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/student/call.errors.no_section')
                ]
            ];
        }
        $now = now();
        $query = ScheduledCall::query()->where('section_id', $sectionId)->with(['subject', 'teacher'])->orderBy('scheduled_at', 'asc');
        $scheduled = $query->get()->map(function (ScheduledCall $s) {
            return [
                'id' => $s->id,
                'subject' => [
                    'id' => $s->subject_id,
                    'name' => optional($s->subject)->name
                ],
                'teacher' => [
                    'id' => $s->created_by,
                    'name' => optional($s->teacher)->name ?? optional($s->teacher->user)->name ?? null
                ],
                'channel_name' => $s->channel_name,
                'call_id' => $s->call->id ?? null,
                'scheduled_at' => $s->scheduled_at ? $s->scheduled_at->toDateTimeString() : null,
                'duration_minutes' => (int) $s->duration_minutes,
                'status' => $s->status
            ];
        })->values();
        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => $scheduled
            ]
        ];
    }
    public function teacherSchedule(array $data, $user): array
    {
        $teacher = $user ? $user->teacher : null;
        if (!$teacher) {
            return [
                'status' => 403,
                'body' => [
                    'status' => false,
                    'message' => __('mobile/teacher/call.errors.not_teacher')
                ]
            ];
        }
        $sectionId = (int) $data['section_id'];
        $subjectId = (int) $data['subject_id'];
        $scheduledAt = Carbon::parse($data['scheduled_at']);
        $duration = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : 30;
        $channel = $data['channel_name'] ?? null;
        $assigned = SectionSubject::where('section_id', $sectionId)->where('subject_id', $subjectId)->where('teacher_id', $teacher->id)->exists();
        if (!$assigned) {
            return [
                'status' => 403,
                'body' => [
                    'status' => false,
                    'message' => __('mobile/teacher/call.errors.section_or_subject_not_assigned')
                ]
            ];
        }
        $newStart = $scheduledAt->copy();
        $newEnd = $scheduledAt->copy()->addMinutes($duration);
        $overlap = ScheduledCall::where('created_by', $teacher->id)->where('status', 'scheduled')->whereRaw('scheduled_at < ?', [$newEnd])->whereRaw('DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?', [$newStart])->exists();
        if ($overlap) {
            return [
                'status' => 422,
                'body' => [
                    'status' => false,
                    'message' => __('mobile/teacher/call.errors.scheduled_overlap'),
                    'errors' => [
                        'scheduled_at' => [__('mobile/teacher/call.errors.scheduled_overlap')]
                    ]
                ]
            ];
        }
        $activeOverlap = Call::where('created_by', $teacher->id)->where(function ($q) use ($newStart, $newEnd) {
            $q->whereNull('ended_at')->where('started_at', '<', $newEnd);
        })->exists();
        if ($activeOverlap) {
            return [
                'status' => 422,
                'body' => [
                    'status' => false,
                    'message' => __('mobile/teacher/call.errors.scheduled_overlap_with_active'),
                    'errors' => [
                        'scheduled_at' => [__('mobile/teacher/call.errors.scheduled_overlap_with_active')]
                    ]
                ]
            ];
        }
        $scheduled = ScheduledCall::create([
            'created_by' => $teacher->id,
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'channel_name' => $channel,
            'scheduled_at' => $newStart,
            'duration_minutes' => $duration,
            'status' => 'scheduled'
        ]);
        return [
            'status' => 201,
            'body' => [
                'status' => true,
                'message' => __('mobile/teacher/call.scheduled_created'),
                'data' => [
                    'scheduled_call_id' => $scheduled->id,
                    'scheduled_at' => $scheduled->scheduled_at->toDateTimeString(),
                    'duration_minutes' => $scheduled->duration_minutes
                ]
            ]
        ];
    }
    public function teacherStartScheduled(ScheduledCall $scheduledCall, $user): array
    {
        $teacher = $user ? $user->teacher : null;
        if (!$teacher) {
            return [
                'status' => 403,
                'body' => [
                    'status' => false,
                    'message' => __('mobile/teacher/call.errors.not_teacher')
                ]
            ];
        }
        if ($scheduledCall->created_by !== $teacher->id) {
            return [
                'status' => 403,
                'body' => [
                    'status' => false,
                    'message' => __('mobile/teacher/call.errors.not_owner_of_scheduled')
                ]
            ];
        }
        if ($scheduledCall->status !== 'scheduled') {
            return [
                'status' => 422,
                'body' => [
                    'status' => false,
                    'message' => __('mobile/teacher/call.errors.invalid_scheduled_status')
                ]
            ];
        }
        $hasActive = Call::where('created_by', $teacher->id)->whereNull('ended_at')->exists();
        if ($hasActive) {
            return [
                'status' => 422,
                'body' => [
                    'status' => false,
                    'message' => __('mobile/teacher/call.errors.active_call_exists'),
                    'errors' => [
                        'active_call' => [__('mobile/teacher/call.errors.active_call_exists')]
                    ]
                ]
            ];
        }
        if (now()->lt($scheduledCall->scheduled_at)) {
            return [
                'status' => 422,
                'body' => [
                    'status' => false,
                    'message' => __('mobile/teacher/call.errors.cannot_start_before_scheduled'),
                    'errors' => [
                        'scheduled_call' => [
                            __('mobile/teacher/call.errors.cannot_start_before_scheduled_detail', [
                                'scheduled_at' => $scheduledCall->scheduled_at->toDateTimeString()
                            ])
                        ],
                        'now' => now()
                    ]
                ]
            ];
        }
        $result = DB::transaction(function () use ($scheduledCall, $teacher, $user) {
            $channel = $scheduledCall->channel_name ?? ($this->zego->generateChannelName() ?? 'call_' . uniqid());
            $call = Call::create([
                'channel_name' => $channel,
                'created_by' => $teacher->id,
                'section_id' => $scheduledCall->section_id,
                'subject_id' => $scheduledCall->subject_id,
                'started_at' => now(),
                'ended_at' => null
            ]);
            \Log::info('JoinCallRequest incoming', [
                'call_id' => $call->id,
                'user_id' => $user?->id
            ]);
            $call->participants()->create([
                'user_id' => $user?->id,
                'joined_at' => now()
            ]);
            $scheduledCall->update([
                'status' => 'started',
                'call_id' => $call->id
            ]);
            $token = null;
            if (isset($this->zego) && method_exists($this->zego, 'generateToken')) {
                $token = $this->zego->generateToken($teacher->user_id);
            }
            return [
                'status' => 201,
                'body' => [
                    'status' => true,
                    'message' => __('mobile/teacher/call.started'),
                    'data' => [
                        'user_id' => $teacher->user_id,
                        'user_name' => $user?->email,
                        'call_id' => $call->id,
                        'channel_name' => $call->channel_name,
                        'token' => $token,
                        'started_at' => $call->started_at->toDateTimeString()
                    ]
                ]
            ];
        });
        return $result;
    }
    public function teacherScheduledCalls($user): array
    {
        $teacher = $user ? $user->teacher : null;
        if (!$teacher) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'message' => __('mobile/teacher/call.errors.not_teacher')
                ]
            ];
        }
        $now = Carbon::now();
        $query = ScheduledCall::query()->where('created_by', $teacher->id)->with(['section', 'subject'])->orderBy('scheduled_at', 'asc');
        $scheduled = $query->get()->map(function (ScheduledCall $s) {
            return [
                'id' => $s->id,
                'section' => [
                    'id' => $s->section_id,
                    'name' => optional($s->section)->name
                ],
                'subject' => [
                    'id' => $s->subject_id,
                    'name' => optional($s->subject)->name
                ],
                'channel_name' => $s->channel_name,
                'scheduled_at' => $s->scheduled_at ? $s->scheduled_at->toDateTimeString() : null,
                'duration_minutes' => (int) $s->duration_minutes,
                'status' => $s->status,
                'call_id' => $s->call_id,
                'created_at' => $s->created_at->toDateTimeString()
            ];
        })->values();
        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => $scheduled
            ]
        ];
    }
    public function teacherEnd(Call $call, $user): array
    {
        $teacherId = $user?->teacher?->id;
        if (!$teacherId) {
            return [
                'status' => 403,
                'body' => [
                    'status' => false,
                    'message' => __('mobile/teacher/call.errors.not_teacher')
                ]
            ];
        }
        if ((int) $call->created_by !== (int) $teacherId) {
            return [
                'status' => 403,
                'body' => [
                    'status' => false,
                    'message' => __('mobile/teacher/call.errors.not_owner_of_call')
                ]
            ];
        }
        if ($call->ended_at !== null) {
            return [
                'status' => 422,
                'body' => [
                    'status' => false,
                    'message' => __('mobile/teacher/call.errors.call_already_ended')
                ]
            ];
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
        return [
            'status' => 200,
            'body' => [
                'status' => true,
                'message' => __('messages.call_ended_successfully')
            ]
        ];
    }
    public function deleteScheduledByTeacher(ScheduledCall $scheduledCall, $user): array
    {
        $teacherId = $user ? $user->teacher?->id : null;
        if (!$teacherId) {
            return [
                'status' => 422,
                'body' => [
                    'message' => __('mobile/teacher/call.errors.not_teacher')
                ]
            ];
        }
        if ((int) $scheduledCall->created_by !== (int) $teacherId) {
            return [
                'status' => 422,
                'body' => [
                    'message' => __('mobile/teacher/call.errors.not_owner')
                ]
            ];
        }
        DB::transaction(function () use ($scheduledCall) {
            $call = null;
            if (method_exists($scheduledCall, 'call')) {
                $call = $scheduledCall->call;
            }
            if (!$call && $scheduledCall->call_id) {
                $call = Call::find($scheduledCall->call_id);
            }
            if ($call && is_null($call->ended_at)) {
                $call->ended_at = now();
                $call->save();
            }
            $scheduledCall->delete();
        });
        return [
            'status' => 200,
            'body' => [
                'message' => __('mobile/teacher/call.messages.deleted')
            ]
        ];
    }
}