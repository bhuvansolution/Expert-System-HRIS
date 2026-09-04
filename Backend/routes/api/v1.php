<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Competency\CompetencyController;
use App\Http\Controllers\Api\V1\Competency\CompetencyLevelController;
use App\Http\Controllers\Api\V1\Competency\EmployeeCompetencyController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Leave\LeaveBalanceController;
use App\Http\Controllers\Api\V1\Leave\LeaveReportController;
use App\Http\Controllers\Api\V1\Leave\LeaveRequestController;
use App\Http\Controllers\Api\V1\Leave\LeaveTypeController;
use App\Http\Controllers\Api\V1\Performance\PerformanceHistoryController;
use App\Http\Controllers\Api\V1\Performance\PerformanceIndicatorController;
use App\Http\Controllers\Api\V1\Performance\PerformancePeriodController;
use App\Http\Controllers\Api\V1\Performance\PerformanceReportController;
use App\Http\Controllers\Api\V1\Performance\PerformanceReviewController;
use App\Http\Controllers\Api\V1\Performance\PerformanceReviewItemController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\PositionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\Training\TrainingController;
use App\Http\Controllers\Api\V1\Training\TrainingParticipantController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::prefix('auth')->name('auth.')->group(function (): void {

    Route::middleware('throttle:5,1')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/me', [ProfileController::class, 'show'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware('auth:sanctum')->group(function (): void {

    // Roles
    Route::apiResource('roles', RoleController::class);

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permission.view');

    // Departments
    Route::apiResource('departments', DepartmentController::class);

    // Positions
    Route::apiResource('positions', PositionController::class);

    // Employees
    Route::apiResource('employees', EmployeeController::class);

    // Attendance
    Route::get('/attendances/recap', [AttendanceController::class, 'recap']);
    Route::get('/attendances/report', [AttendanceController::class, 'report']);
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::get('/attendances/{attendance}', [AttendanceController::class, 'show']);
    Route::post('/attendances/clock-in', [AttendanceController::class, 'clockIn']);
    Route::post('/attendances/clock-out', [AttendanceController::class, 'clockOut']);

    //Leave Types
    Route::apiResource('leave-types', LeaveTypeController::class);

    //Leave Balances
    Route::get('/leave-balances/me', [LeaveBalanceController::class, 'me']);
    Route::get('/leave-balances', [LeaveBalanceController::class, 'index']);
    Route::get('/leave-balances/{employee}', [LeaveBalanceController::class, 'employee']);

    //Leave Requests
    Route::get('/leave-requests/me', [LeaveRequestController::class, 'me']);
    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::post('/leave-requests/{leaveRequest}/approve',  [LeaveRequestController::class, 'approve']);
    Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);
    Route::post('/leave-requests/{leaveRequest}/cancel',  [LeaveRequestController::class, 'cancel']);

    //Leave Reports
    Route::get('/leave-reports', [LeaveReportController::class, 'index']);

    Route::prefix('performance')->group(function () {

        // Performance Period
        Route::apiResource('periods', PerformancePeriodController::class);

        // Performance Indicator
        Route::get('indicators/active', [PerformanceIndicatorController::class, 'active']);
        Route::apiResource('indicators', PerformanceIndicatorController::class);

        //Performance Review
        Route::post('reviews/{performanceReview}/calculate', [PerformanceReviewController::class, 'calculate']);
        Route::post('reviews/{performanceReview}/submit', [PerformanceReviewController::class, 'submit']);
        Route::post('reviews/{performanceReview}/approve', [PerformanceReviewController::class, 'approve']);
        Route::post('reviews/{performanceReview}/reject', [PerformanceReviewController::class, 'reject']);

        Route::apiResource('reviews', PerformanceReviewController::class);

        // Performance Review Items
        Route::scopeBindings()->group(function (): void {
            Route::get('reviews/{performanceReview}/items', [PerformanceReviewItemController::class, 'index']);
            Route::post('reviews/{performanceReview}/items', [PerformanceReviewItemController::class, 'store']);
            Route::get('reviews/{performanceReview}/items/{performanceReviewItem}', [PerformanceReviewItemController::class, 'show']);
            Route::put('reviews/{performanceReview}/items/{performanceReviewItem}', [PerformanceReviewItemController::class, 'update']);
            Route::patch('reviews/{performanceReview}/items/{performanceReviewItem}', [PerformanceReviewItemController::class, 'update']);
            Route::delete('reviews/{performanceReview}/items/{performanceReviewItem}', [PerformanceReviewItemController::class, 'destroy']);
        });

        // Performance History
        Route::get('history', [PerformanceHistoryController::class, 'index']);
        Route::get('history/employees/{employee}', [PerformanceHistoryController::class, 'employee']);

        //Performance Report
        Route::get('reports',  [PerformanceReportController::class, 'index']);
    });

    //Competency
    Route::apiResource('competencies', CompetencyController::class);

    // Competency Level
    Route::apiResource('competency-levels', CompetencyLevelController::class);

    // Employee Competency
    Route::apiResource('employee-competencies', EmployeeCompetencyController::class);

    // Training
    Route::apiResource('trainings', TrainingController::class);
    Route::patch('trainings/{training}/status',   [TrainingController::class, 'updateStatus']);

    Route::get('training-participants/history/{employeeId}',  [TrainingParticipantController::class, 'history']);
    Route::apiResource('training-participants', TrainingParticipantController::class);
    Route::post('training-participants/{trainingParticipant}/evaluate', [TrainingParticipantController::class, 'evaluate']);
});
