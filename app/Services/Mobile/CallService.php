<?php

namespace App\Services\Mobile;

use App\Models\ScheduledCall;
use Illuminate\Support\Facades\Auth;

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
        $scheduledCall->delete();
    }
}