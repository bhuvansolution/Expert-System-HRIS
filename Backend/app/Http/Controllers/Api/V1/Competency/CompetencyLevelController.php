<?php

namespace App\Http\Controllers\Api\V1\Competency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Competency\StoreCompetencyLevelRequest;
use App\Http\Requests\Competency\UpdateCompetencyLevelRequest;
use App\Http\Resources\V1\Competency\CompetencyLevelResource;
use App\Models\CompetencyLevel;
use App\Services\Competency\CompetencyLevelService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CompetencyLevelController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CompetencyLevelService $competencyLevelService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:competency-level.view', only: ['index', 'show']),
            new Middleware('permission:competency-level.create', only: ['store']),
            new Middleware('permission:competency-level.update', only: ['update']),
            new Middleware('permission:competency-level.delete', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $search = trim((string) $request->query('search', ''));

        $competencyLevels = $this->competencyLevelService->paginate(
            perPage: $perPage,
            search: $search,
        );

        return ApiResponse::success(
            data: CompetencyLevelResource::collection($competencyLevels),
            message: 'Daftar competency level berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $competencyLevels->currentPage(),
                    'last_page' => $competencyLevels->lastPage(),
                    'per_page' => $competencyLevels->perPage(),
                    'total' => $competencyLevels->total(),
                    'from' => $competencyLevels->firstItem(),
                    'to' => $competencyLevels->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompetencyLevelRequest $request): JsonResponse
    {
        $competencyLevel = $this->competencyLevelService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: CompetencyLevelResource::make($competencyLevel),
            message: 'Competency level berhasil dibuat.',
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(CompetencyLevel $competencyLevel): JsonResponse
    {
        return ApiResponse::success(
            data: CompetencyLevelResource::make($competencyLevel),
            message: 'Detail competency level berhasil diambil.',
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompetencyLevelRequest $request, CompetencyLevel $competencyLevel): JsonResponse
    {
        $competencyLevel = $this->competencyLevelService->update(
            $competencyLevel,
            $request->validated(),
        );

        return ApiResponse::success(
            data: CompetencyLevelResource::make($competencyLevel),
            message: 'Competency Level berhasil diperbarui.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CompetencyLevel $competencyLevel): JsonResponse
    {
        $this->competencyLevelService->delete($competencyLevel);

        return ApiResponse::success(
            data: null,
            message: 'Competency Level berhasil dihapus.',
        );
    }
}
