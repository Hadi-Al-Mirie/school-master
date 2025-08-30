<?php

use App\Http\Controllers\Api\V1\Mobile\Student\StudentCallController;
use App\Http\Controllers\Api\V1\Mobile\Teacher\TeacherExamAttemptController;
use App\Http\Controllers\Api\V1\Mobile\Teacher\TeacherQuizController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Mobile\Auth\MobileAuthController;
use App\Http\Controllers\Api\V1\Mobile\Teacher\TeacherCallController;
use App\Http\Controllers\Api\V1\Mobile\Teacher\TeacherHomeController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use App\Http\Controllers\Api\V1\Mobile\Teacher\TeacherStudentsController;
use App\Http\Controllers\Api\V1\Mobile\Teacher\TeacherScheduleController;
use App\Http\Controllers\Api\V1\Mobile\Teacher\TeacherNoteController;
use App\Http\Controllers\Api\V1\Mobile\Teacher\TeacherDictationController;
use App\Http\Controllers\Api\V1\Mobile\Supervisor\SupervisorStudentsController;
use App\Http\Controllers\Api\V1\Mobile\Supervisor\SupervisorNoteController;
use App\Http\Controllers\Api\V1\Mobile\Supervisor\SupervisorAttendanceController;
use App\Http\Controllers\Api\V1\Mobile\Supervisor\SupervisorExamController;
use App\Http\Controllers\Api\V1\Mobile\Supervisor\SupervisorClassroomController;
use App\Http\Controllers\Api\V1\Mobile\Supervisor\SupervisorNoteApprovalController;
use App\Http\Controllers\Api\V1\Mobile\Supervisor\SupervisorExamApprovalController;
use App\Http\Controllers\Api\V1\Mobile\Student\StudentNoteController;
use App\Http\Controllers\Api\V1\Mobile\Student\StudentAttendanceController;
use App\Http\Controllers\Api\V1\Mobile\Student\StudentDictationController;
use App\Http\Controllers\Api\V1\Mobile\Student\StudentQuizController;
use App\Http\Controllers\Api\V1\Mobile\Student\StudentHomeController;
use App\Http\Controllers\Api\V1\Mobile\Student\StudentExamController;
use App\Http\Controllers\Api\V1\Mobile\Student\StudentQuizSubmissionController;
use App\Http\Controllers\Api\V1\Mobile\Student\StudentScheduleController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['api', 'localize'],
], function () {
    Route::post('v1/mobile/login', [MobileAuthController::class, 'login'])
        ->name('login');

    Route::group([
        'prefix' => 'v1/mobile/teacher',
        'middleware' => ['auth:sanctum', 'IsTeacher', 'EnsureActiveSemesterExist'],
    ], function () {
        Route::get('home', [TeacherHomeController::class, 'index']);
        Route::get('students', [TeacherStudentsController::class, 'index']);
        Route::get('weekly-schedule', [TeacherScheduleController::class, 'weekly']);
        Route::post('call/schedule', [TeacherCallController::class, 'schedule']);
        Route::get('call/scheduled-calls', [TeacherCallController::class, 'scheduledCalls']);
        Route::post('scheduled-call/{scheduled_call}/start', [TeacherCallController::class, 'startScheduled']);
        Route::post('call/{call}/end', [TeacherCallController::class, 'end']);
        Route::delete('scheduled-call/{scheduled_call}', [TeacherCallController::class, 'destroyScheduled']);
        Route::post('notes/create', [TeacherNoteController::class, 'store']);
        Route::post('dictations/create', [TeacherDictationController::class, 'store']);
        Route::post('quiz/create', [TeacherQuizController::class, 'store']);
        Route::get('exams/enterable', [TeacherExamAttemptController::class, 'enterable']);
        Route::post('exams/{exam}/results', [TeacherExamAttemptController::class, 'submitResults']);
    });
    Route::group([
        'prefix' => 'v1/mobile/student',
        'middleware' => ['auth:sanctum', 'IsStudent'],
    ], function () {
        Route::get('home', [StudentHomeController::class, 'index']);
        Route::get('notes', [StudentNoteController::class, 'index']);
        Route::get('attendances', [StudentAttendanceController::class, 'index']);
        Route::get('dictations', [StudentDictationController::class, 'index']);
        Route::get('quizzes', [StudentQuizController::class, 'index']);
        Route::get('exams', [StudentExamController::class, 'index']);
        Route::get('quizzes/submittable', [StudentQuizSubmissionController::class, 'submittable']);
        Route::get('quizzes/{quiz}/questions', [StudentQuizSubmissionController::class, 'questions']);
        Route::post('quizzes/{quiz}/submit', [StudentQuizSubmissionController::class, 'submit']);
        Route::get('call/scheduled-calls', [StudentCallController::class, 'scheduledCalls']);
        Route::post('call/join', [StudentCallController::class, 'join']);
        Route::get('schedule/weekly', [StudentScheduleController::class, 'weekly']); // NEW
    });
    Route::group([
        'prefix' => 'v1/mobile/supervisor',
        'middleware' => ['auth:sanctum', 'IsSupervisor'],
    ], function () {
        Route::get('students', [SupervisorStudentsController::class, 'index']);
        Route::post('notes/create', [SupervisorNoteController::class, 'store']);
        Route::post('attendance/register', [SupervisorAttendanceController::class, 'store']);
        Route::post('exams/create', [SupervisorExamController::class, 'store']);
        Route::get('exams/waiting', [SupervisorExamApprovalController::class, 'waiting']);
        Route::get('exams/{exam}/attempts', [SupervisorExamApprovalController::class, 'attempts']);
        Route::post('exams/{exam}/finalize', [SupervisorExamApprovalController::class, 'finalize']);
        Route::get('classrooms/with-subjects', [SupervisorClassroomController::class, 'classroomsWithSubjects']);
    });
});
