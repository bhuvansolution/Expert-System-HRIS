<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\StorePerformanceIndicatorRequest;
use App\Http\Requests\Performance\UpdatePerformanceIndicatorRequest;
use App\Http\Resources\V1\Performance\PerformanceIndicatorResource;
use App\Models\PerformanceIndicator;
use App\Services\Performance\PerformanceIndicatorService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PerformanceIndicatorController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PerformanceIndicatorService $performanceIndicatorService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:performance_indicator.view', only: ['index', 'show', 'active']),
            new Middleware('permission:performance_indicator.create', only: ['store']),
            new Middleware('permission:performance_indicator.update', only: ['update']),
            new Middleware('permission:performance_indicator.delete', only: ['destroy']),
        ];
    }

    public function index(): JsonResponse
    {
        $indicators = $this->performanceIndicatorService->getAll();

        return ApiResponse::success(
            data: PerformanceIndicatorResource::collection($indicators),
            message: 'Performance indicator berhasil diambil.',
        );
    }

    public function active(): JsonResponse
    {
        $indicators = $this->performanceIndicatorService->getActive();

        return ApiResponse::success(
            data: PerformanceIndicatorResource::collection($indicators),
            message: 'Performance indicator aktif berhasil diambil.',
        );
    }

    public function store(
        StorePerformanceIndicatorRequest $request
    ): JsonResponse {
        $indicator = $this->performanceIndicatorService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformanceIndicatorResource($indicator),
            message: 'Performance indicator berhasil dibuat.',
            status: 201,
        );
    }

    public function show(
        PerformanceIndicator $performanceIndicator
    ): JsonResponse {
        $indicator = $this->performanceIndicatorService->getById(
            $performanceIndicator->id,
        );

        return ApiResponse::success(
            data: new PerformanceIndicatorResource($indicator),
            message: 'Performance indicator berhasil diambil.',
        );
    }

    public function update(
        UpdatePerformanceIndicatorRequest $request,
        PerformanceIndicator $performanceIndicator
    ): JsonResponse {
        $indicator = $this->performanceIndicatorService->update(
            $performanceIndicator,
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformanceIndicatorResource($indicator),
            message: 'Performance indicator berhasil diperbarui.',
        );
    }

    public function destroy(
        PerformanceIndicator $performanceIndicator
    ): JsonResponse {
        $this->performanceIndicatorService->delete(
            $performanceIndicator,
        );

        return ApiResponse::success(
            message: 'Performance indicator berhasil dihapus.',
            status: 204,
        );
    }
}
