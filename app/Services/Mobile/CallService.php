<?php

namespace App\Services\Mobile;

use App\Models\ScheduledCall;
use Illuminate\Support\Facades\Auth;
use App\Models\Call;
use Illuminate\Support\Facades\DB;
class CallService
{
    /**
     * Delete a scheduled call if it belongs to the current teacher and is still "scheduled".
     */
    public function deleteScheduledByTeacher(ScheduledCall $scheduledCall): void
    {
        $teacherId = Auth::user()?->teacher?->id;
        if (!$teacherId) {
            throw new \RuntimeException(__('mobile/teacher/call.errors.not_teacher'));
        }
        if ((int) $scheduledCall->created_by !== (int) $teacherId) {
            throw new \RuntimeException(__('mobile/teacher/call.errors.not_owner'));
        }
        // if ($scheduledCall->status !== 'scheduled') {
        //     throw new \RuntimeException(__('mobile/teacher/call.errors.not_deletable'));
        // }
        DB::transaction(function () use ($scheduledCall) {
            // The scheduled call may or may not have an associated call
            $call = null;
            // If there is a relation defined: $scheduledCall->call
            if (method_exists($scheduledCall, 'call')) {
                $call = $scheduledCall->call;
            }
            // Fallback by foreign key if relation isn’t loaded/defined
            if (!$call && $scheduledCall->call_id) {
                $call = Call::find($scheduledCall->call_id);
            }
            // If a call exists, mark it ended (only if not already ended)
            if ($call && is_null($call->ended_at)) {
                $call->ended_at = now();
                $call->save();
            }
            // Finally remove the scheduled call entry
            $scheduledCall->delete();
        });
    }
}
