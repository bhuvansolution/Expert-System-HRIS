<?php

namespace App\Http\Controllers\Api\V1\Competency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Competency\StoreCompetencyRequest;
use App\Http\Requests\Competency\UpdateCompetencyRequest;
use App\Http\Resources\V1\Competency\CompetencyResource;
use App\Models\Competency;
use App\Services\Competency\CompetencyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CompetencyController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CompetencyService $competencyService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:competency.view', only: ['index', 'show']),
            new Middleware('permission:competency.create', only: ['store']),
            new Middleware('permission:competency.update', only: ['update']),
            new Middleware('permission:competency.delete', only: ['destroy']),
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

        $competencies = $this->competencyService->paginate(
            perPage: $perPage,
            search: $search,
        );

        return ApiResponse::success(
            data: CompetencyResource::collection($competencies),
            message: 'Daftar competency berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $competencies->currentPage(),
                    'last_page' => $competencies->lastPage(),
                    'per_page' => $competencies->perPage(),
                    'total' => $competencies->total(),
                    'from' => $competencies->firstItem(),
                    'to' => $competencies->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompetencyRequest $request): JsonResponse
    {
        $competency = $this->competencyService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: CompetencyResource::make($competency),
            message: 'Competency berhasil dibuat.',
        );
    }


    /**
     * Display the specified resource.
     */
    public function show(Competency $competency): JsonResponse
    {
        return ApiResponse::success(
            data: CompetencyResource::make($competency),
            message: 'Detail competency berhasil diambil.',
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateCompetencyRequest $request,
        Competency $competency,
    ): JsonResponse {
        $competency = $this->competencyService->update(
            $competency,
            $request->validated(),
        );

        return ApiResponse::success(
            data: CompetencyResource::make($competency),
            message: 'Competency berhasil diperbarui.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Competency $competency): JsonResponse
    {
        $this->competencyService->delete($competency);

        return ApiResponse::success(
            data: null,
            message: 'Competency berhasil dihapus.',
        );
    }
}
