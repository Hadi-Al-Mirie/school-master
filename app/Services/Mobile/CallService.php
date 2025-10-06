<?php

namespace App\Services\Mobile;

use App\Models\ScheduledCall;
use Illuminate\Support\Facades\Auth;
use App\Models\Call;
use Illuminate\Support\Facades\DB;
class CallService
{
    public function deleteScheduledByTeacher(ScheduledCall $scheduledCall): void
    {
        $teacherId = Auth::user()?->teacher?->id;
        if (!$teacherId) {
            throw new \RuntimeException(__('mobile/teacher/call.errors.not_teacher'));
        }
        if ((int) $scheduledCall->created_by !== (int) $teacherId) {
            throw new \RuntimeException(__('mobile/teacher/call.errors.not_owner'));
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
    }
}