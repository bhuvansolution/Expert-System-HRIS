<?php

namespace App\Http\Controllers\Api\V1\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveTypeIndexRequest;
use App\Http\Requests\Leave\StoreLeaveTypeRequest;
use App\Http\Requests\Leave\UpdateLeaveTypeRequest;
use App\Http\Resources\V1\Leave\LeaveTypeResource;
use App\Models\LeaveType;
use App\Services\Leave\LeaveTypeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LeaveTypeController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly LeaveTypeService $leaveTypeService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:leave_type.view', only: ['index', 'show']),
            new Middleware('permission:leave_type.create', only: ['store']),
            new Middleware('permission:leave_type.update', only: ['update']),
            new Middleware('permission:leave_type.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(LeaveTypeIndexRequest $request): JsonResponse
    {
        $leaveTypes = $this->leaveTypeService->paginate(
            perPage: $request->integer('per_page', 15),
            search: $request->query('search'),
        );

        return ApiResponse::success(
            data: LeaveTypeResource::collection($leaveTypes),
            message: 'Daftar Leave Type berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $leaveTypes->currentPage(),
                    'last_page' => $leaveTypes->lastPage(),
                    'per_page' => $leaveTypes->perPage(),
                    'total' => $leaveTypes->total(),
                    'from' => $leaveTypes->firstItem(),
                    'to' => $leaveTypes->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        $leaveType = $this->leaveTypeService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: LeaveTypeResource::make($leaveType),
            message: 'Leave Type berhasil dibuat.',
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(LeaveType $leaveType): JsonResponse
    {
        $leaveType = $this->leaveTypeService->findById(
            $leaveType->id,
        );

        return ApiResponse::success(
            data: LeaveTypeResource::make($leaveType),
            message: 'Detail Leave Type berhasil diambil.',
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateLeaveTypeRequest $request,
        LeaveType $leaveType,
    ): JsonResponse {
        $leaveType = $this->leaveTypeService->update(
            $leaveType,
            $request->validated(),
        );

        return ApiResponse::success(
            data: LeaveTypeResource::make($leaveType),
            message: 'Leave Type berhasil diperbarui.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LeaveType $leaveType): JsonResponse
    {
        $this->leaveTypeService->delete($leaveType);

        return ApiResponse::success(
            data: null,
            message: 'Leave Type berhasil dihapus.',
        );
    }
}
