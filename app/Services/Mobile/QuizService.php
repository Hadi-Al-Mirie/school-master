<?php

namespace App\Services\Mobile;

use App\Models\QuizAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use App\Models\Quiz;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionAnswer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QuizService
{
    public function index(int $userId, array $filters = []): array
    {
        $student = Student::with('user:id,first_name,last_name')
            ->where('user_id', $userId)
            ->first();
        if (!$student) {
            return ['ok' => false, 'message' => __('mobile/student/quizzes.errors.student_not_found')];
        }
        $activeYear = Year::where('is_active', true)->first();
        if (!$activeYear) {
            return ['ok' => false, 'message' => __('mobile/student/quizzes.errors.active_year_not_found')];
        }
        $semesterIds = Semester::where('year_id', $activeYear->id)->pluck('id');
        $q = QuizAttempt::query()
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quiz_attempts.student_id', $student->id)
            ->whereIn('quizzes.semester_id', $semesterIds)
            ->when(!empty($filters['semester_id']) && $semesterIds->contains((int) $filters['semester_id']), fn($qq) => $qq->where('quizzes.semester_id', (int) $filters['semester_id']))
            ->when(!empty($filters['quiz_id']), fn($qq) => $qq->where('quiz_attempts.quiz_id', (int) $filters['quiz_id']))
            ->when(!empty($filters['subject_id']), fn($qq) => $qq->where('quizzes.subject_id', (int) $filters['subject_id']))
            ->when(!empty($filters['teacher_id']), fn($qq) => $qq->where('quizzes.teacher_id', (int) $filters['teacher_id']))
            ->when(isset($filters['min_score']), fn($qq) => $qq->where('quiz_attempts.total_score', '>=', (float) $filters['min_score']))
            ->when(isset($filters['max_score']), fn($qq) => $qq->where('quiz_attempts.total_score', '<=', (float) $filters['max_score']))
            ->when(!empty($filters['submitted_from']), fn($qq) => $qq->whereDate('quiz_attempts.submitted_at', '>=', $filters['submitted_from']))
            ->when(!empty($filters['submitted_to']), fn($qq) => $qq->whereDate('quiz_attempts.submitted_at', '<=', $filters['submitted_to']));
        switch ($filters['sort'] ?? 'newest') {
            case 'oldest':
                $q->orderBy('quiz_attempts.submitted_at', 'asc')->orderBy('quiz_attempts.id', 'asc');
                break;
            case 'highest_score':
                $q->orderByDesc(DB::raw('COALESCE(quiz_attempts.total_score,0)'));
                break;
            case 'lowest_score':
                $q->orderBy(DB::raw('COALESCE(quiz_attempts.total_score,0)'));
                break;
            default:
                $q->orderBy('quiz_attempts.submitted_at', 'desc')->orderBy('quiz_attempts.id', 'desc');
        }
        $attempts = $q->get([
            'quiz_attempts.id',
            'quiz_attempts.quiz_id',
            'quiz_attempts.total_score',
            'quiz_attempts.submitted_at',
            'quizzes.id as q_id',
            'quizzes.name as quiz_name',
            'quizzes.semester_id as q_semester_id',
            'quizzes.subject_id as q_subject_id',
            'quizzes.teacher_id as q_teacher_id',
        ]);
        $quizIds = $attempts->pluck('q_id')->unique()->values();
        $subjects = DB::table('subjects')->whereIn('id', $attempts->pluck('q_subject_id')->filter()->unique())->get(['id', 'name'])->keyBy('id');
        $semesters = DB::table('semesters')->whereIn('id', $attempts->pluck('q_semester_id')->unique())->get(['id', 'name'])->keyBy('id');
        $teacherIds = $attempts->pluck('q_teacher_id')->filter()->unique();
        $teachers = DB::table('teachers')->whereIn('id', $teacherIds)->get(['id', 'user_id'])->keyBy('id');
        $userIds = $teachers->pluck('user_id')->unique()->filter();
        $users = DB::table('users')->whereIn('id', $userIds)->get(['id', 'first_name', 'last_name', 'email'])->keyBy('id');
        $scope = QuizAttempt::query()
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quiz_attempts.student_id', $student->id)
            ->whereIn('quizzes.semester_id', $semesterIds)
            ->when(!empty($filters['semester_id']) && $semesterIds->contains((int) $filters['semester_id']), fn($qq) => $qq->where('quizzes.semester_id', (int) $filters['semester_id']))
            ->when(!empty($filters['quiz_id']), fn($qq) => $qq->where('quiz_attempts.quiz_id', (int) $filters['quiz_id']))
            ->when(!empty($filters['subject_id']), fn($qq) => $qq->where('quizzes.subject_id', (int) $filters['subject_id']))
            ->when(!empty($filters['teacher_id']), fn($qq) => $qq->where('quizzes.teacher_id', (int) $filters['teacher_id']))
            ->when(isset($filters['min_score']), fn($qq) => $qq->where('quiz_attempts.total_score', '>=', (float) $filters['min_score']))
            ->when(isset($filters['max_score']), fn($qq) => $qq->where('quiz_attempts.total_score', '<=', (float) $filters['max_score']))
            ->when(!empty($filters['submitted_from']), fn($qq) => $qq->whereDate('quiz_attempts.submitted_at', '>=', $filters['submitted_from']))
            ->when(!empty($filters['submitted_to']), fn($qq) => $qq->whereDate('quiz_attempts.submitted_at', '<=', $filters['submitted_to']));
        $attemptsCount = (int) (clone $scope)->count();
        $avgScore = (float) (clone $scope)->avg('quiz_attempts.total_score');
        $sumScore = (float) (clone $scope)->sum('quiz_attempts.total_score');
        $bestRow = (clone $scope)->orderByDesc('quiz_attempts.total_score')->first();
        $lastRow = (clone $scope)->orderByDesc('quiz_attempts.id')->first();
        $items = $attempts->map(function ($row) use ($subjects, $semesters, $teachers, $users) {
            $teacher = $row->q_teacher_id ? ($teachers[$row->q_teacher_id] ?? null) : null;
            $teacherUser = $teacher ? ($users[$teacher->user_id] ?? null) : null;
            return [
                'attempt_id' => (int) $row->id,
                'score' => (float) $row->total_score,
                'submitted_at' => $row->submitted_at,
                'quiz' => [
                    'id' => (int) $row->q_id,
                    'name' => $row->quiz_name,
                    'semester' => ($s = $semesters[$row->q_semester_id] ?? null) ? ['id' => (int) $s->id, 'name' => $s->name] : null,
                    'subject' => ($sub = $subjects[$row->q_subject_id] ?? null) ? ['id' => (int) $sub->id, 'name' => $sub->name] : null,
                    'teacher' => $teacherUser ? [
                        'id' => (int) $row->q_teacher_id,
                        'name' => trim(($teacherUser->first_name ?? '') . ' ' . ($teacherUser->last_name ?? '')),
                        'email' => $teacherUser->email ?? null,
                    ] : null,
                ],
            ];
        });

        return [
            'ok' => true,
            'message' => __('mobile/student/quizzes.success.loaded'),
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->user ? ($student->user->first_name . ' ' . $student->user->last_name) : null,
                ],
                'year' => ['id' => $activeYear->id, 'name' => $activeYear->name],
                'summary' => [
                    'attempts' => $attemptsCount,
                    'avg_score' => $avgScore,
                    'sum_score' => $sumScore,
                    'best' => $bestRow ? [
                        'attempt_id' => (int) $bestRow->id,
                        'score' => (float) $bestRow->total_score,
                    ] : null,
                    'last' => $lastRow ? [
                        'attempt_id' => (int) $lastRow->id,
                        'score' => (float) $lastRow->total_score,
                        'submitted_at' => $lastRow->submitted_at,
                    ] : null,
                ],
                'attempts' => $items,
                'count' => $items->count(),
            ],
        ];
    }

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
                $qq->where('section_id', $student->section_id)
                    ->orWhere(function ($q2) use ($student) {
                        $q2->whereNull('section_id')
                            ->where('classroom_id', $student->classroom_id);
                    });
            })
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

    public function getQuizForStudent(int $userId, Quiz $quiz): array
    {
        $student = Student::with(['section:id,classroom_id'])
            ->where('user_id', $userId)->first();

        if (!$student) {
            return ['ok' => false, 'code' => 404, 'message' => __('mobile/student/quiz_submission.errors.student_not_found')];
        }
        if (!$this->quizIsForStudent($quiz, $student)) {
            return ['ok' => false, 'code' => 403, 'message' => __('mobile/student/quiz_submission.errors.forbidden_quiz')];
        }
        $now = Carbon::now();
        if (empty($quiz->end_time) || $quiz->end_time->gt($now)) {
            return ['ok' => false, 'code' => 403, 'message' => __('mobile/student/quiz_submission.errors.not_yet_submittable')];
        }
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
                $q->select(['id', 'text', 'quiz_question_id']);
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

    public function submit(int $userId, Quiz $quiz, array $answers): array
    {
        $student = Student::where('user_id', $userId)->first();
        if (!$student) {
            return ['ok' => false, 'code' => 404, 'message' => __('mobile/student/quiz_submission.errors.student_not_found')];
        }
        if (!$this->quizIsForStudent($quiz, $student)) {
            return ['ok' => false, 'code' => 403, 'message' => __('mobile/student/quiz_submission.errors.forbidden_quiz')];
        }
        $now = Carbon::now();
        if (empty($quiz->end_time) || $quiz->end_time->gt($now)) {
            return ['ok' => false, 'code' => 403, 'message' => __('mobile/student/quiz_submission.errors.not_yet_submittable')];
        }
        $exists = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->whereNotNull('submitted_at')
            ->exists();
        if ($exists) {
            return ['ok' => false, 'code' => 409, 'message' => __('mobile/student/quiz_submission.errors.already_submitted')];
        }
        $quiz->load('questions.answers');
        $questionIds = $quiz->questions->pluck('id')->values();
        $payloadQuestionIds = collect($answers)->pluck('question_id')->values();
        $missing = $questionIds->diff($payloadQuestionIds);
        $extra = $payloadQuestionIds->diff($questionIds);
        if ($missing->isNotEmpty() || $extra->isNotEmpty()) {
            return ['ok' => false, 'code' => 422, 'message' => __('mobile/student/quiz_submission.errors.must_include_all_questions')];
        }
        if ($payloadQuestionIds->unique()->count() !== $payloadQuestionIds->count()) {
            return ['ok' => false, 'code' => 422, 'message' => __('mobile/student/quiz_submission.errors.duplicate_questions')];
        }
        $allowedAnswerIdsByQuestion = $quiz->questions->mapWithKeys(function (QuizQuestion $qq) {
            return [$qq->id => $qq->answers->pluck('id')->values()];
        });
        $result = DB::transaction(function () use ($student, $quiz, $answers, $allowedAnswerIdsByQuestion, $now) {
            $attempt = QuizAttempt::firstOrCreate(
                ['quiz_id' => $quiz->id, 'student_id' => $student->id],
                ['started_at' => $now]
            );
            QuizAttemptAnswer::where('quiz_attempt_id', $attempt->id)->delete();
            $rowsToInsert = [];
            foreach ($answers as $row) {
                $qid = (int) $row['question_id'];
                $aid = $row['answer_id'] !== null ? (int) $row['answer_id'] : null;
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
            $total = DB::table('quiz_attempt_answers as qaa')
                ->join('quiz_question_answers as a', 'a.id', '=', 'qaa.quiz_question_answer_id')
                ->join('quiz_questions as q', 'q.id', '=', 'qaa.quiz_question_id')
                ->where('qaa.quiz_attempt_id', $attempt->id)
                ->where('a.correct', true)
                ->sum('q.mark');
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

    public function teacherStoreQuiz(array $data, $user): array
    {
        $teacher = $user ? $user->teacher : null;
        if (!$teacher) {
            return [
                'status' => 403,
                'body' => [
                    'message' => __('mobile/teacher/quiz.errors.not_teacher')
                ]
            ];
        }
        $sectionSubjectExists = \App\Models\SectionSubject::where('section_id', $data['section_id'])->where('subject_id', $data['subject_id'])->where('teacher_id', $teacher->id)->exists();
        if (!$sectionSubjectExists) {
            return [
                'status' => 403,
                'body' => [
                    'message' => __('mobile/teacher/quiz.errors.teacher_not_assigned')
                ]
            ];
        }
        $questions = $data['questions'];
        $totalMark = 0;
        foreach ($questions as $qIndex => $q) {
            $qMark = isset($q['mark']) ? floatval($q['mark']) : 0;
            $totalMark += $qMark;
            if (!isset($q['answers']) || !is_array($q['answers']) || count($q['answers']) < 2) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => __('mobile/teacher/quiz.errors.min_answers_per_question'),
                        'question_index' => $qIndex
                    ]
                ];
            }
            $correctCount = 0;
            foreach ($q['answers'] as $a) {
                if (!array_key_exists('is_correct', $a)) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => __('mobile/teacher/quiz.errors.answer_correct_flag'),
                            'question_index' => $qIndex
                        ]
                    ];
                }
                if ($a['is_correct']) {
                    $correctCount++;
                }
            }
            if ($correctCount !== 1) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => __('mobile/teacher/quiz.errors.one_correct_answer'),
                        'question_index' => $qIndex,
                        'correct_count' => $correctCount
                    ]
                ];
            }
        }
        if ($totalMark > 20) {
            return [
                'status' => 422,
                'body' => [
                    'message' => __('mobile/teacher/quiz.errors.total_mark_exceeded'),
                    'total' => $totalMark
                ]
            ];
        }
        $quiz = DB::transaction(function () use ($teacher, $data, $questions) {
            $semester = Semester::where('is_active', true)->firstOrFail();
            $quiz = Quiz::create([
                'teacher_id' => $teacher->id,
                'subject_id' => $data['subject_id'],
                'classroom_id' => $data['classroom_id'],
                'section_id' => $data['section_id'],
                'semester_id' => $semester->id,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'name' => $data['name']
            ]);
            foreach ($questions as $q) {
                $qq = QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'text' => $q['question'],
                    'mark' => $q['mark']
                ]);
                foreach ($q['answers'] as $a) {
                    QuizQuestionAnswer::create([
                        'quiz_question_id' => $qq->id,
                        'text' => $a['answer'],
                        'correct' => (bool) $a['is_correct']
                    ]);
                }
            }
            return $quiz;
        });
        return [
            'status' => 201,
            'body' => [
                'message' => __('mobile/teacher/quiz.created'),
                'quiz_id' => $quiz->id
            ]
        ];
    }


    private function quizIsForStudent(Quiz $quiz, Student $student): bool
    {
        return (int) $quiz->section_id === (int) $student->section_id
            || ($quiz->section_id === null && (int) $quiz->classroom_id === (int) $student->classroom_id);
    }
}