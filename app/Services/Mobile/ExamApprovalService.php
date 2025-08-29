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
    /**
     * Exams in the supervisor's stage with status "wait" (not released yet).
     */
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

    /**
     * Attempts for an exam (default pending) with student info.
     */
    public function examAttempts(Exam $exam, string $status = 'pending'): Collection
    {
        return ExamAttempt::query()
            ->with(['student:id,user_id,section_id', 'student.user:id,first_name,last_name'])
            ->where('exam_id', $exam->id)
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderBy('id')
            ->get(['id', 'exam_id', 'student_id', 'result', 'status', 'created_at']);
    }

    /**
     * Finalize/approve results for an exam and release it.
     * - Ensures exam belongs to supervisor's stage.
     * - Ensures full section submitted.
     * - Validates results are within bounds (0..max_result).
     * - Updates attempts (result + approved) and sets exam status -> released.
     *
     * @param array<int, array{attempt_id:int,result:float|int}> $attempts
     */
    public function finalize(Exam $exam, array $attempts): array
    {
        $supervisor = Supervisor::where('user_id', Auth::id())->first();
        if (!$supervisor) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.supervisor_not_found'));
        }

        // Ownership / stage guard
        if (!$exam->relationLoaded('section')) {
            $exam->load('section.classroom');
        }
        if ((int) $exam->section->classroom->stage_id !== (int) $supervisor->stage_id) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.exam_not_in_stage'));
        }
        if ($exam->status === 'released') {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.exam_already_released'));
        }

        // Fetch all attempts for this exam (we'll compare coverage)
        $allAttempts = ExamAttempt::where('exam_id', $exam->id)->get(['id', 'student_id', 'result', 'status']);
        if ($allAttempts->isEmpty()) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.no_attempts'));
        }

        // Section population (students expected)
        $sectionStudentCount = $exam->section->students()->count();
        if ($sectionStudentCount === 0) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.section_empty'));
        }

        // Map for quick access
        $allById = $allAttempts->keyBy('id');

        // Validate provided list references only this exam
        foreach ($attempts as $row) {
            if (!isset($allById[$row['attempt_id']])) {
                throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.attempt_not_belong'));
            }
        }

        // Apply edited results (but do not save yet)
        $max = (float) ($exam->max_result ?? 100);

        foreach ($attempts as $row) {
            $val = (float) $row['result'];

            // Primary cap check: results must be within 0..max_result
            if ($val < 0 || $val > $max) {
                throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.result_out_of_bounds', ['min' => 0, 'max' => $max]));
            }

            // If you intended "must be under MIN", enable the next line and adjust message:
            // if ($val > $min) { throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.result_over_min', ['min' => $min])); }

            $allById[$row['attempt_id']]->result = $val;
        }

        // Ensure full section submitted: there must be an attempt per student in section
        $distinctStudentsInAttempts = $allAttempts->pluck('student_id')->unique()->count();
        if ($distinctStudentsInAttempts !== $sectionStudentCount) {
            throw new \RuntimeException(__('mobile/supervisor/exam_approval.errors.incomplete_section'));
        }

        // Save everything atomically
        return DB::transaction(function () use ($allAttempts, $allById, $attempts, $exam) {
            // Update edited attempts + status -> approved
            foreach ($attempts as $row) {
                $attempt = $allById[$row['attempt_id']];
                $attempt->status = 'approved';
                $attempt->save();
            }

            // Any remaining pending attempts (not in the edited list) also need approval
            // if you want to approve all submitted ones:
            $pendingOthers = $allAttempts->where('status', 'pending');
            foreach ($pendingOthers as $attempt) {
                $attempt->status = 'approved';
                $attempt->save();
            }

            // Release the exam for the section
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