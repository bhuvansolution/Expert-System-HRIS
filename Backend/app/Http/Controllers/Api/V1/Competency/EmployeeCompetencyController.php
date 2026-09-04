<?php

namespace App\Http\Controllers\Api\V1\Competency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Competency\StoreEmployeeCompetencyRequest;
use App\Http\Requests\Competency\UpdateEmployeeCompetencyRequest;
use App\Http\Resources\V1\Competency\EmployeeCompetencyResource;
use App\Models\EmployeeCompetency;
use App\Services\Competency\EmployeeCompetencyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EmployeeCompetencyController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly EmployeeCompetencyService $employeeCompetencyService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:employee-competency.view', only: ['index', 'show']),
            new Middleware('permission:employee-competency.create', only: ['store']),
            new Middleware('permission:employee-competency.update', only: ['update']),
            new Middleware('permission:employee-competency.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100,);
        $search = trim((string) $request->query('search', ''));

        $employeeCompetencies = $this->employeeCompetencyService->paginate(
            perPage: $perPage,
            search: $search,
        );

        return ApiResponse::success(
            data: EmployeeCompetencyResource::collection($employeeCompetencies),
            message: 'Daftar Employee Competency berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $employeeCompetencies->currentPage(),
                    'last_page' => $employeeCompetencies->lastPage(),
                    'per_page' => $employeeCompetencies->perPage(),
                    'total' => $employeeCompetencies->total(),
                    'from' => $employeeCompetencies->firstItem(),
                    'to' => $employeeCompetencies->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeCompetencyRequest $request): JsonResponse
    {
        $employeeCompetency = $this->employeeCompetencyService->create(
            $request->validated(),
        );
        return ApiResponse::success(
            data: new EmployeeCompetencyResource($employeeCompetency),
            message: 'Employee Competency berhasil ditambahkan.',
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(EmployeeCompetency $employeeCompetency): JsonResponse
    {
        return ApiResponse::success(
            data: new EmployeeCompetencyResource($employeeCompetency),
            message: 'Employee Competency berhasil diambil.',
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateEmployeeCompetencyRequest $request,
        EmployeeCompetency $employeeCompetency
    ): JsonResponse {
        $employeeCompetency = $this->employeeCompetencyService->update(
            $employeeCompetency,
            $request->validated(),
        );

        return ApiResponse::success(
            data: new EmployeeCompetencyResource($employeeCompetency),
            message: 'Employee Competency berhasil diperbarui.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeeCompetency $employeeCompetency): JsonResponse
    {
        $this->employeeCompetencyService->delete($employeeCompetency);

        return ApiResponse::success(
            message: 'Employee Competency berhasil dihapus.',
        );
    }
}
