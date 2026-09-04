<?php

namespace App\Http\Controllers\Api\V1\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveRequestIndexRequest;
use App\Http\Requests\Leave\RejectLeaveRequestRequest;
use App\Http\Requests\Leave\StoreLeaveRequestRequest;
use App\Http\Resources\V1\Leave\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Leave\LeaveRequestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LeaveRequestController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly LeaveRequestService $leaveRequestService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:leave_request.view', only: ['me', 'index', 'show']),
            new Middleware('permission:leave_request.create', only: ['store']),
            new Middleware('permission:leave_request.approve', only: ['approve']),
            new Middleware('permission:leave_request.reject', only: ['reject']),
            new Middleware('permission:leave_request.cancel', only: ['cancel']),
        ];
    }

    public function index(LeaveRequestIndexRequest $request): JsonResponse
    {
        $leaveRequests = $this->leaveRequestService->paginate(
            perPage: $request->integer('per_page', 15),
            employeeId: $request->integer('employee_id') ?: null,
            leaveTypeId: $request->integer('leave_type_id') ?: null,
            year: $request->integer('year') ?: null,
            status: $request->query('status'),
        );

        return ApiResponse::success(
            data: LeaveRequestResource::collection($leaveRequests),
            message: 'Daftar Leave Request berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $leaveRequests->currentPage(),
                    'last_page' => $leaveRequests->lastPage(),
                    'per_page' => $leaveRequests->perPage(),
                    'total' => $leaveRequests->total(),
                    'from' => $leaveRequests->firstItem(),
                    'to' => $leaveRequests->lastItem(),
                ],
            ],
        );
    }

    public function me(LeaveRequestIndexRequest $request): JsonResponse
    {
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

        $leaveRequests = $this->leaveRequestService->getMyRequests(
            employee: $employee,
            perPage: $request->integer('per_page', 15),
            status: $request->query('status'),
            year: $request->integer('year') ?: null,
        );

        return ApiResponse::success(
            data: LeaveRequestResource::collection($leaveRequests),
            message: 'Leave Request berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $leaveRequests->currentPage(),
                    'last_page' => $leaveRequests->lastPage(),
                    'per_page' => $leaveRequests->perPage(),
                    'total' => $leaveRequests->total(),
                    'from' => $leaveRequests->firstItem(),
                    'to' => $leaveRequests->lastItem(),
                ],
            ],
        );
    }

    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest = $this->leaveRequestService->findById(
            $leaveRequest->id
        );

        return ApiResponse::success(
            data: new LeaveRequestResource($leaveRequest),
            message: 'Leave Request berhasil diambil.',
        );
    }

    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
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

        $leaveRequest = $this->leaveRequestService->create(
            employee: $employee,
            data: $request->validated(),
        );

        return ApiResponse::success(
            data: new LeaveRequestResource($leaveRequest),
            message: 'Pengajuan cuti berhasil dibuat.',
            status: 201,
        );
    }

    public function approve(LeaveRequest $leaveRequest): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $leaveRequest = $this->leaveRequestService->approve(
            leaveRequest: $leaveRequest,
            approvedBy: $user,
        );

        return ApiResponse::success(
            data: new LeaveRequestResource($leaveRequest),
            message: 'Pengajuan cuti berhasil disetujui.',
        );
    }

    public function reject(
        RejectLeaveRequestRequest $request,
        LeaveRequest $leaveRequest
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::user();

        $leaveRequest = $this->leaveRequestService->reject(
            leaveRequest: $leaveRequest,
            rejectedBy: $user,
            rejectionReason: $request->validated('rejection_reason'),
        );

        return ApiResponse::success(
            data: new LeaveRequestResource($leaveRequest),
            message: 'Pengajuan cuti berhasil ditolak.',
        );
    }

    public function cancel(LeaveRequest $leaveRequest): JsonResponse
    {
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

        $leaveRequest = $this->leaveRequestService->cancel(
            leaveRequest: $leaveRequest,
            employee: $employee,
        );

        return ApiResponse::success(
            data: new LeaveRequestResource($leaveRequest),
            message: 'Pengajuan cuti berhasil dibatalkan.',
        );
    }
}
