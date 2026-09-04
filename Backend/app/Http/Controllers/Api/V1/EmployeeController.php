<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\EmployeeIndexRequest;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\V1\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EmployeeController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:employee.view', only: ['index', 'show']),
            new Middleware('permission:employee.create', only: ['store']),
            new Middleware('permission:employee.update', only: ['update']),
            new Middleware('permission:employee.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(EmployeeIndexRequest $request): JsonResponse
    {
        $employees = $this->employeeService->paginate(
            perPage: $request->integer('per_page', 15),
            search: $request->query('search'),
            departmentId: $request->integer('department_id') ?: null,
            positionId: $request->integer('position_id') ?: null,
            managerId: $request->integer('manager_id') ?: null,
            employmentType: $request->query('employment_type'),
            employmentStatus: $request->query('employment_status'),
        );

        return ApiResponse::success(
            data: EmployeeResource::collection($employees),
            message: 'Daftar Employee berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'per_page' => $employees->perPage(),
                    'total' => $employees->total(),
                    'from' => $employees->firstItem(),
                    'to' => $employees->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employeeService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: EmployeeResource::make($employee),
            message: 'Employee berhasil dibuat.',
        );
    }
    /**
     * Display the specified resource.
     */
    public function show(Employee $employee): JsonResponse
    {
        $employee = $this->employeeService->findById(
            $employee->id,
        );

        return ApiResponse::success(
            data: EmployeeResource::make($employee),
            message: 'Detail Employee berhasil diambil.',
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeService->update(
            $employee,
            $request->validated(),
        );

        return ApiResponse::success(
            data: EmployeeResource::make($employee),
            message: 'Employee berhasil diperbarui.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $this->employeeService->delete($employee);

        return ApiResponse::success(
            data: null,
            message: 'Employee berhasil dihapus.'
        );
    }
}
