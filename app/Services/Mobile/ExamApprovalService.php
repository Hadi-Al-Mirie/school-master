<?php

namespace App\Services\Mobile;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Supervisor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamApprovalService
{

    public function waitingExamsForSupervisorStage(): Collection
    {
        $supervisor = Supervisor::where('user_id', Auth::id())->first();
        if (!$supervisor) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.supervisor_not_found'));
        }
        return Exam::query()
            ->with(['section:id,name,classroom_id', 'section.classroom:id,name,stage_id', 'subject:id,name'])
            ->where('status', 'wait')
            ->whereHas('section.classroom', function ($q) use ($supervisor) {
                $q->where('stage_id', $supervisor->stage_id);
            })
            ->orderByDesc('id')
            ->get([
                'id',
                'name',
                'status',
                'section_id',
                'subject_id',
                'semester_id',
                'max_result',
                'created_by'
            ]);
    }


    public function examAttempts(Exam $exam, string $status = 'pending'): Collection
    {
        return ExamAttempt::query()
            ->with(['student:id,user_id,section_id', 'student.user:id,first_name,last_name'])
            ->where('exam_id', $exam->id)
            ->orderBy('id')
            ->get(['id', 'exam_id', 'student_id', 'result', 'status', 'created_at']);
    }

    public function finalize(Exam $exam, array $attempts): array
    {
        $supervisor = Supervisor::where('user_id', Auth::id())->first();
        if (!$supervisor) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.supervisor_not_found'));
        }
        if (!$exam->relationLoaded('section')) {
            $exam->load('section.classroom');
        }
        if ((int) $exam->section->classroom->stage_id !== (int) $supervisor->stage_id) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.exam_not_in_stage'));
        }
        if ($exam->status === 'released') {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.exam_already_released'));
        }
        $allAttempts = ExamAttempt::where('exam_id', $exam->id)->get(['id', 'student_id', 'result', 'status']);
        if ($allAttempts->isEmpty()) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.no_attempts'));
        }
        $sectionStudentCount = $exam->section->students()->count();
        if ($sectionStudentCount === 0) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.section_empty'));
        }
        $allById = $allAttempts->keyBy('id');
        foreach ($attempts as $row) {
            if (!isset($allById[$row['attempt_id']])) {
                throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.attempt_not_belong'));
            }
        }
        $max = (float) ($exam->max_result ?? 100);
        foreach ($attempts as $row) {
            $val = (float) $row['result'];
            if ($val < 0 || $val > $max) {
                throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.result_out_of_bounds', ['min' => 0, 'max' => $max]));
            }
            $allById[$row['attempt_id']]->result = $val;
        }
        $distinctStudentsInAttempts = $allAttempts->pluck('student_id')->unique()->count();
        if ($distinctStudentsInAttempts !== $sectionStudentCount) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.incomplete_section'));
        }
        return DB::transaction(function () use ($allAttempts, $allById, $attempts, $exam) {
            foreach ($attempts as $row) {
                $attempt = $allById[$row['attempt_id']];
                $attempt->status = 'approved';
                $attempt->save();
            }
            $pendingOthers = $allAttempts->where('status', 'pending');
            foreach ($pendingOthers as $attempt) {
                $attempt->status = 'approved';
                $attempt->save();
            }
            $exam->status = 'released';
            $exam->save();
            return [
                'approved' => $allAttempts->count(),
                'exam' => [
                    'id' => $exam->id,
                    'status' => $exam->status,
                    'section_id' => $exam->section_id,
                ],
            ];
        });
    }
}