<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Mobile\AuthController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use App\Http\Controllers\Api\V1\Dashboard\AuthController as DashboardAuthController;
use App\Http\Controllers\Api\V1\Dashboard\HomeController as DashboardHomeController;
use App\Http\Controllers\Api\V1\Dashboard\StudentController as DashboardStudentController;
use App\Http\Controllers\Api\V1\Dashboard\StudentController;
use App\Http\Controllers\Api\V1\Dashboard\TeacherController;
use App\Http\Controllers\Api\V1\Dashboard\SupervisorController as SupervisorController;
use App\Http\Controllers\Api\V1\Dashboard\SearchController as SearchController;
use App\Http\Controllers\TeacherController as DashboardTeacherController;
use App\Http\Controllers\SessionYearController;
use App\Http\Controllers\Api\V1\Dashboard\SemesterController as DashboardSemesterController;
use App\Http\Controllers\Api\V1\Dashboard\YearController as DashboardYearController;
use App\Http\Controllers\Api\V1\Dashboard\EventsController as DashboardEventsController;
use App\Http\Controllers\Api\V1\Dashboard\StageController as DashboardStageController;
use App\Http\Controllers\Api\V1\Dashboard\ScheduleController;
use App\Http\Controllers\Api\V1\Dashboard\ClassroomController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['api', 'localize'],
], function () {
    Route::post('v1/dashboard/forgot-password', [DashboardAuthController::class, 'sendPasswordResetLink']);
    Route::post('v1/dashboard/reset-password', [DashboardAuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('v1/dashboard/logout', [DashboardAuthController::class, 'logout']);
    });

    Route::post('v1/dashboard/login', [DashboardAuthController::class, 'login'])
        ->name('dashboard.login');

});

Route::prefix('v1/dashboard')->middleware('auth:sanctum')->group(function () {
    Route::get('/home', [DashboardHomeController::class, 'index']);
    Route::get('/classrooms', [ClassroomController::class, 'index']);
    Route::apiResource('/student', StudentController::class);
    Route::apiResource('/teacher', TeacherController::class);
    Route::apiResource('/supervisor', SupervisorController::class);
    Route::post('search', [SearchController::class, 'index']);
    Route::get('/semester', [DashboardSemesterController::class, 'index']);
    Route::post('/semester', [DashboardSemesterController::class, 'store']);
    Route::post('/years', [DashboardYearController::class, 'store']);
    Route::get('/years', [DashboardYearController::class, 'index']);
    Route::post('/events', [DashboardEventsController::class, 'store']);
    Route::get('/get-stages', [DashboardStageController::class, 'index']);
    Route::get('/get-stages-only', [DashboardStageController::class, 'indexStagesOnly']);
    Route::post('schedule/initialize-weekly', [ScheduleController::class, 'initializeWeekly']);
    Route::get('schedule/periods', [ScheduleController::class, 'periods']);
    Route::get('schedule/generate', [ScheduleController::class, 'generate']);
    Route::post('schedule/reset', [ScheduleController::class, 'reset']);
    Route::get('schedule/status', [ScheduleController::class, 'status']);
    Route::get('schedule/export', [ScheduleController::class, 'export']);
});
