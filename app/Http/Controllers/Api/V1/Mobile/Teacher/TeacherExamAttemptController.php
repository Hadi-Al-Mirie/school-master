<?php

namespace App\Http\Controllers\Api\V1\Mobile\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Mobile\Teacher\SubmitExamResultsRequest;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\SectionSubject;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;

class TeacherExamAttemptController extends Controller
{
    public function enterable(Request $request): JsonResponse
    {
        $user = Auth::user();
        $teacher = $user ? $user->teacher : null;
        if (!$teacher) {
            return response()->json(['success' => false, 'message' => __('mobile/teacher/exam.errors.not_teacher')], 403);
        }
        $exams = Exam::query()
            ->where('status', Exam::STATUS_WAIT)
            ->whereExists(function ($q) use ($teacher) {
                $q->select(DB::raw(1))
                    ->from('section_subjects')
                    ->whereColumn('section_subjects.section_id', 'exams.section_id')
                    ->whereColumn('section_subjects.subject_id', 'exams.subject_id')
                    ->where('section_subjects.teacher_id', $teacher->id);
            })
            ->with(['section', 'subject'])
            ->get()
            ->map(function ($ex) {
                return [
                    'id' => $ex->id,
                    'name' => $ex->name,
                    'classroom' => $ex->section->classroom->name,
                    'section_id' => $ex->section->id,
                    'section_name' => $ex->section->name,
                    'subject_id' => $ex->subject->id,
                    'subject_name' => $ex->subject->name,
                    'semester' => $ex->semester->name,
                    'max_result' => $ex->max_result
                ];
            })->values();
        return response()->json(['success' => true, 'data' => $exams]);
    }

    public function submitResults(SubmitExamResultsRequest $request, Exam $exam): JsonResponse
    {
        $user = Auth::user();
        $teacher = $user ? $user->teacher : null;
        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => __('mobile/teacher/exam.errors.not_teacher'),
            ], 403);
        }
        if ($exam->status !== Exam::STATUS_WAIT) {
            return response()->json([
                'success' => false,
                'message' => __('mobile/teacher/exam.errors.exam_not_open'),
            ], 422);
        }
        $assigned = SectionSubject::where('section_id', $exam->section_id)
            ->where('subject_id', $exam->subject_id)
            ->where('teacher_id', $teacher->id)
            ->exists();
        if (!$assigned) {
            return response()->json([
                'success' => false,
                'message' => __('mobile/teacher/exam.errors.teacher_not_assigned'),
            ], 403);
        }
        if ($exam->max_result === null) {
            return response()->json([
                'success' => false,
                'message' => __('mobile/teacher/exam.errors.max_result_not_set'),
            ], 422);
        }
        $maxAllowed = (float) $exam->max_result;
        $payload = $request->validated();
        $results = $payload['results'] ?? [];
        $errors = new MessageBag();
        foreach ($results as $idx => $row) {
            $studentId = $row['student_id'] ?? null;
            $resultVal = $row['result'] ?? null;
            $student = Student::where('id', $studentId)
                ->where('section_id', $exam->section_id)
                ->first();
            if (!$student) {
                $errors->add("results.$idx.student_id", __('mobile/teacher/exam.errors.student_not_in_section'));
                continue;
            }
            if (!is_numeric($resultVal)) {
                $errors->add("results.$idx.result", __('mobile/teacher/exam.validation.result_numeric'));
                continue;
            }
            if ((float) $resultVal > $maxAllowed) {
                $errors->add("results.$idx.result", __('mobile/teacher/exam.validation.result_max', ['max' => $maxAllowed]));
            }
        }
        if ($errors->any()) {
            return response()->json([
                'success' => false,
                'message' => __('mobile/teacher/exam.validation_failed'),
                'errors' => $errors->messages(),
            ], 422);
        }
        try {
            DB::transaction(function () use ($results, $exam, $teacher, $maxAllowed) {
                foreach ($results as $row) {
                    $studentId = $row['student_id'];
                    $resultVal = (float) $row['result'];
                    $attempt = ExamAttempt::updateOrCreate(
                        [
                            'exam_id' => $exam->id,
                            'student_id' => $studentId,
                        ],
                        [
                            'teacher_id' => $teacher->id,
                            'result' => $resultVal,
                            'status' => ExamAttempt::STATUS_WAIT,
                            'updated_at' => now(),
                        ]
                    );
                }
            });

            return response()->json([
                'success' => true,
                'message' => __('mobile/teacher/exam.store.success'),
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Failed to store exam results', ['error' => $e->getMessage(), 'exam_id' => $exam->id]);
            return response()->json([
                'success' => false,
                'message' => __('mobile/teacher/exam.store.error'),
            ], 500);
        }
    }
}
