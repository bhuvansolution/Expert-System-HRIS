<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceIndexRequest;
use App\Http\Requests\Attendance\AttendanceReportRequest;
use App\Http\Resources\V1\AttendanceResource;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AttendanceController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly AttendanceService $attendanceService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:attendance.clock_in', only: ['clockIn']),
            new Middleware('permission:attendance.clock_out', only: ['clockOut']),
            new Middleware('permission:attendance.view',  only: ['index', 'show']),
            new Middleware('permission:attendance.view_all', only: ['recap']),
            new Middleware('permission:attendance.report', only: ['report']),
        ];
    }
    /**
     * Clock in authenticated employee.
     */
    public function clockIn(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $employee = $user->employee;

        $attendance = $this->attendanceService->clockIn($employee);

        return ApiResponse::success(
            data: AttendanceResource::make($attendance),
            message: 'Berhasil Clock in.',
        );
    }

    /**
     * Clock out authenticated employee.
     */
    public function clockOut(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $employee = $user->employee;

        $attendance = $this->attendanceService->clockOut($employee);

        return ApiResponse::success(
            data: AttendanceResource::make($attendance),
            message: 'Berhasil Clock Out.',
        );
    }

    /**
     * Get attendance history.
     */
    public function index(
        AttendanceIndexRequest $request
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::user();

        $employee = $user->employee;
        $viewAll = $user->can('attendance.view_all');

        $attendances = $this->attendanceService->getAll(
            $request->validated(),
            $employee,
            $viewAll
        );

        return ApiResponse::success(
            data: AttendanceResource::collection($attendances),
            message: 'Daftar Attendance berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $attendances->currentPage(),
                    'last_page' => $attendances->lastPage(),
                    'per_page' => $attendances->perPage(),
                    'total' => $attendances->total(),
                    'from' => $attendances->firstItem(),
                    'to' => $attendances->lastItem(),
                ],
            ],
        );
    }

    /**
     * Get attendance detail.
     */
    public function show(Attendance $attendance): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $employee = $user->employee;
        $viewAll = $user->can('attendance.view_all');

        $attendance = $this->attendanceService->getById(
            $attendance->id,
            $employee,
            $viewAll
        );

        return ApiResponse::success(
            data: AttendanceResource::make($attendance),
            message: 'Detail Attendance berhasil diambil.',
        );
    }

    /**
     * Get attendance recap.
     */
    public function recap(
        AttendanceIndexRequest $request
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::user();

        $employee = $user->employee;
        $viewAll = $user->can('attendance.view_all');

        $recap = $this->attendanceService->getRecap(
            $request->validated(),
            $employee,
            $viewAll
        );

        return ApiResponse::success(
            data: $recap,
            message: 'Attendance recap berhasil diambil.',
        );
    }

    /**
     * Get attendance report.
     */
    public function report(
        AttendanceReportRequest $request
    ): JsonResponse {
        $report = $this->attendanceService->getReport(
            $request->validated()
        );

        return ApiResponse::success(
            data: $report,
            message: 'Attendance report berhasil diambil.',
        );
    }
}
