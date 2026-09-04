<?php

namespace App\Http\Controllers\Api\V1\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveBalanceIndexRequest;
use App\Http\Resources\V1\Leave\LeaveBalanceResource;
use App\Models\Employee;
use App\Models\User;
use App\Services\Leave\LeaveBalanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LeaveBalanceController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly LeaveBalanceService $leaveBalanceService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:leave_balance.view', only: ['me']),
            new Middleware('permission:leave_balance.view_all', only: ['index', 'employee']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(
        LeaveBalanceIndexRequest $request
    ): JsonResponse {
        $leaveBalances = $this->leaveBalanceService->paginate(
            perPage: $request->integer('per_page', 15),
            employeeId: $request->integer('employee_id') ?: null,
            leaveTypeId: $request->integer('leave_type_id') ?: null,
            year: $request->integer('year') ?: null,
        );

        return ApiResponse::success(
            data: LeaveBalanceResource::collection($leaveBalances),
            message: 'Daftar Leave Balance berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $leaveBalances->currentPage(),
                    'last_page' => $leaveBalances->lastPage(),
                    'per_page' => $leaveBalances->perPage(),
                    'total' => $leaveBalances->total(),
                    'from' => $leaveBalances->firstItem(),
                    'to' => $leaveBalances->lastItem(),
                ],
            ],
        );
    }

    /**
     * Display authenticated employee leave balances.
     */
    public function me(
        LeaveBalanceIndexRequest $request
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::user();

        /** @var Employee|null $employee */
        $employee = $user->employee;

        if (!$employee) {
            return ApiResponse::error(
                message: 'User tidak memiliki data employee.',
                status: 422,
            );
        }

        $leaveBalances = $this->leaveBalanceService->getMyBalances(
            employee: $employee,
            year: $request->integer('year') ?: null,
        );

        return ApiResponse::success(
            data: LeaveBalanceResource::collection($leaveBalances),
            message: 'Leave Balance berhasil diambil.',
        );
    }

    /**
     * Display leave balances for a specific employee.
     */
    public function employee(
        LeaveBalanceIndexRequest $request,
        Employee $employee
    ): JsonResponse {
        $leaveBalances = $this->leaveBalanceService->getByEmployee(
            employee: $employee,
            year: $request->integer('year') ?: null,
        );

        return ApiResponse::success(
            data: LeaveBalanceResource::collection($leaveBalances),
            message: 'Leave Balance employee berhasil diambil.',
        );
    }
}
