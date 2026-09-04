<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Resources\V1\DepartmentResource;
use App\Models\Department;
use App\Services\DepartmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DepartmentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly DepartmentService $departmentService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:department.view', only: ['index', 'show']),
            new Middleware('permission:department.create', only: ['store']),
            new Middleware('permission:department.update', only: ['update']),
            new Middleware('permission:department.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1),  100,);
        $search = trim((string) $request->query('search', ''));

        $departments = $this->departmentService->paginate(
            perPage: $perPage,
            search: $search,
        );

        return ApiResponse::success(
            data: DepartmentResource::collection($departments),
            message: 'Daftar department berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $departments->currentPage(),
                    'last_page' => $departments->lastPage(),
                    'per_page' => $departments->perPage(),
                    'total' => $departments->total(),
                    'from' => $departments->firstItem(),
                    'to' => $departments->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $this->departmentService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: DepartmentResource::make($department),
            message: 'Department berhasil dibuat.',
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department): JsonResponse
    {
        return ApiResponse::success(
            data: DepartmentResource::make($department),
            message: 'Detail department berhasil diambil.',
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $department = $this->departmentService->update(
            $department,
            $request->validated(),
        );

        return ApiResponse::success(
            data: DepartmentResource::make($department),
            message: 'Department berhasil diperbarui.',
        );
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department): JsonResponse
    {
        $this->departmentService->delete($department);

        return ApiResponse::success(
            data: null,
            message: 'Department berhasil dihapus.',
        );
    }
}
