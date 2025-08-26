<?php

namespace App\Services\Mobile;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionAnswer;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentQuizSubmissionService
{
    /**
     * List quizzes the student can submit:
     *  - quiz.end_time <= now (per your spec)
     *  - quiz is for the student's section (or classroom if section is null)
     *  - student has NOT already submitted this quiz
     */
    public function submittableQuizzes(int $userId, array $filters = []): array
    {
        $student = Student::with(['section:id,classroom_id', 'classroom:id'])
            ->where('user_id', $userId)->first();

        if (!$student) {
            return ['ok' => false, 'message' => __('mobile/student/quiz_submission.errors.student_not_found')];
        }

        $now = Carbon::now();

        $q = Quiz::query()
            ->withCount('questions')
            ->with(['subject:id,name'])
            ->whereNotNull('end_time')
            ->where('end_time', '<=', $now)
            ->where(function ($qq) use ($student) {
                // for the student's section OR (section is null and matches student's classroom)
                $qq->where('section_id', $student->section_id)
                    ->orWhere(function ($q2) use ($student) {
                    $q2->whereNull('section_id')
                        ->where('classroom_id', $student->classroom_id);
                });
            })
            // exclude quizzes already submitted by this student
            ->whereDoesntHave('attempts', function ($qa) use ($student) {
                $qa->where('student_id', $student->id)
                    ->whereNotNull('submitted_at');
            });

        if (!empty($filters['subject_id'])) {
            $q->where('subject_id', (int) $filters['subject_id']);
        }

        switch ($filters['sort'] ?? 'newest') {
            case 'oldest':
                $q->orderBy('end_time', 'asc');
                break;
            case 'ending_soon':
                $q->orderBy('end_time', 'asc');
                break;
            default:
                $q->orderBy('end_time', 'desc');
                break;
        }

        $items = $q->get()->map(function (Quiz $quiz) {
            return [
                'id' => $quiz->id,
                'name' => $quiz->name,
                'subject' => $quiz->subject ? ['id' => $quiz->subject->id, 'name' => $quiz->subject->name] : null,
                'start_time' => $quiz->start_time,
                'end_time' => $quiz->end_time,
                'total_questions' => (int) $quiz->questions_count,
            ];
        });

        return [
            'ok' => true,
            'message' => __('mobile/student/quiz_submission.success.submittable_loaded'),
            'data' => [
                'count' => $items->count(),
                'quizzes' => $items,
            ],
        ];
    }

    /**
     * Fetch a quiz with its questions & answer choices (no 'correct' flags).
     * Also checks the student is allowed to submit it (same rules as above).
     */
    public function getQuizForStudent(int $userId, Quiz $quiz): array
    {
        $student = Student::with(['section:id,classroom_id'])
            ->where('user_id', $userId)->first();

        if (!$student) {
            return ['ok' => false, 'code' => 404, 'message' => __('mobile/student/quiz_submission.errors.student_not_found')];
        }

        // Access checks
        if (!$this->quizIsForStudent($quiz, $student)) {
            return ['ok' => false, 'code' => 403, 'message' => __('mobile/student/quiz_submission.errors.forbidden_quiz')];
        }

        $now = Carbon::now();
        if (empty($quiz->end_time) || $quiz->end_time->gt($now)) {
            // per spec: can submit ONLY when end_time <= now
            return ['ok' => false, 'code' => 403, 'message' => __('mobile/student/quiz_submission.errors.not_yet_submittable')];
        }

        // Already submitted?
        $already = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->whereNotNull('submitted_at')
            ->exists();

        if ($already) {
            return ['ok' => false, 'code' => 409, 'message' => __('mobile/student/quiz_submission.errors.already_submitted')];
        }

        $quiz->load([
            'subject:id,name',
            'questions.answers' => function ($q) {
                $q->select(['id', 'text', 'quiz_question_id']); // no 'correct'
            }
        ]);

        $questions = $quiz->questions->map(function (QuizQuestion $qq) {
            return [
                'id' => $qq->id,
                'text' => $qq->text,
                'mark' => (int) $qq->mark,
                'answers' => $qq->answers->map(fn(QuizQuestionAnswer $a) => [
                    'id' => $a->id,
                    'text' => $a->text,
                ]),
            ];
        });

        return [
            'ok' => true,
            'message' => __('mobile/student/quiz_submission.success.quiz_loaded'),
            'data' => [
                'quiz' => [
                    'id' => $quiz->id,
                    'name' => $quiz->name,
                    'subject' => $quiz->subject ? ['id' => $quiz->subject->id, 'name' => $quiz->subject->name] : null,
                    'start_time' => $quiz->start_time,
                    'end_time' => $quiz->end_time,
                    'questions' => $questions,
                    'count' => $questions->count(),
                ],
            ],
        ];
    }

    /**
     * Submit answers for ALL questions of a quiz.
     * - `answers` is an array of {question_id, answer_id|null}
     * - If answer_id is null, the question is left blank
     * - Computes total score by summing marks for correct selections
     * - Upserts a QuizAttempt, writes QuizAttemptAnswers, sets submitted_at, and returns final score
     */
    public function submit(int $userId, Quiz $quiz, array $answers): array
    {
        $student = Student::where('user_id', $userId)->first();
        if (!$student) {
            return ['ok' => false, 'code' => 404, 'message' => __('mobile/student/quiz_submission.errors.student_not_found')];
        }

        // Access checks
        if (!$this->quizIsForStudent($quiz, $student)) {
            return ['ok' => false, 'code' => 403, 'message' => __('mobile/student/quiz_submission.errors.forbidden_quiz')];
        }

        $now = Carbon::now();
        if (empty($quiz->end_time) || $quiz->end_time->gt($now)) {
            return ['ok' => false, 'code' => 403, 'message' => __('mobile/student/quiz_submission.errors.not_yet_submittable')];
        }

        // Already submitted?
        $exists = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->whereNotNull('submitted_at')
            ->exists();

        if ($exists) {
            return ['ok' => false, 'code' => 409, 'message' => __('mobile/student/quiz_submission.errors.already_submitted')];
        }

        // Load quiz questions and build maps
        $quiz->load('questions.answers');
        $questionIds = $quiz->questions->pluck('id')->values();

        // Validate that payload covers ALL quiz questions exactly once
        $payloadQuestionIds = collect($answers)->pluck('question_id')->values();
        $missing = $questionIds->diff($payloadQuestionIds);
        $extra = $payloadQuestionIds->diff($questionIds);
        if ($missing->isNotEmpty() || $extra->isNotEmpty()) {
            return ['ok' => false, 'code' => 422, 'message' => __('mobile/student/quiz_submission.errors.must_include_all_questions')];
        }
        // Ensure no duplicates
        if ($payloadQuestionIds->unique()->count() !== $payloadQuestionIds->count()) {
            return ['ok' => false, 'code' => 422, 'message' => __('mobile/student/quiz_submission.errors.duplicate_questions')];
        }

        // Build [question_id => [answer_ids... allowed]] to validate answer ownership
        $allowedAnswerIdsByQuestion = $quiz->questions->mapWithKeys(function (QuizQuestion $qq) {
            return [$qq->id => $qq->answers->pluck('id')->values()];
        });

        // Transaction: upsert attempt, write answers, compute total
        $result = DB::transaction(function () use ($student, $quiz, $answers, $allowedAnswerIdsByQuestion, $now) {

            // Create or reuse a draft attempt (no submitted_at)
            $attempt = QuizAttempt::firstOrCreate(
                ['quiz_id' => $quiz->id, 'student_id' => $student->id],
                ['started_at' => $now]
            );

            // Clear any previous answers for this attempt (if retrying before submit)
            QuizAttemptAnswer::where('quiz_attempt_id', $attempt->id)->delete();

            // Insert all answers (including nulls)
            $rowsToInsert = [];
            foreach ($answers as $row) {
                $qid = (int) $row['question_id'];
                $aid = $row['answer_id'] !== null ? (int) $row['answer_id'] : null;

                // Validate answer belongs to question
                if ($aid !== null && !$allowedAnswerIdsByQuestion[$qid]->contains($aid)) {
                    throw new \RuntimeException(__('mobile/student/quiz_submission.errors.answer_not_belongs'));
                }

                $rowsToInsert[] = [
                    'quiz_attempt_id' => $attempt->id,
                    'quiz_question_id' => $qid,
                    'quiz_question_answer_id' => $aid,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (!empty($rowsToInsert)) {
                DB::table('quiz_attempt_answers')->insert($rowsToInsert);
            }

            // Compute total score: sum marks where selected answer is correct
            $total = DB::table('quiz_attempt_answers as qaa')
                ->join('quiz_question_answers as a', 'a.id', '=', 'qaa.quiz_question_answer_id')
                ->join('quiz_questions as q', 'q.id', '=', 'qaa.quiz_question_id')
                ->where('qaa.quiz_attempt_id', $attempt->id)
                ->where('a.correct', true)
                ->sum('q.mark');

            // Finalize attempt
            $attempt->total_score = (int) $total;
            $attempt->submitted_at = $now;
            $attempt->save();

            return [
                'quiz_id' => $quiz->id,
                'final_result' => (int) $total,
            ];
        });

        return [
            'ok' => true,
            'message' => __('mobile/student/quiz_submission.success.submitted'),
            'data' => $result,
        ];
    }

    private function quizIsForStudent(Quiz $quiz, Student $student): bool
    {
        // Quiz is allowed when:
        // - section_id == student's section_id
        // - OR section_id is null AND classroom_id == student's classroom_id
        return (int) $quiz->section_id === (int) $student->section_id
            || ($quiz->section_id === null && (int) $quiz->classroom_id === (int) $student->classroom_id);
    }
}