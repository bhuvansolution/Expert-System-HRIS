<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\StorePerformanceReviewItemRequest;
use App\Http\Requests\Performance\UpdatePerformanceReviewItemRequest;
use App\Http\Resources\V1\Performance\PerformanceReviewItemResource;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewItem;
use App\Services\Performance\PerformanceReviewItemService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PerformanceReviewItemController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PerformanceReviewItemService $performanceReviewItemService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:performance_review.view', only: ['index', 'show']),
            new Middleware('permission:performance_review.update', only: ['store', 'update']),
            new Middleware('permission:performance_review.delete', only: ['destroy']),
        ];
    }

    public function index(
        PerformanceReview $performanceReview
    ): JsonResponse {
        Gate::authorize('view', $performanceReview);

        $items = $this->performanceReviewItemService->getByReview(
            $performanceReview,
        );

        return ApiResponse::success(
            data: PerformanceReviewItemResource::collection($items),
            message: 'Performance review item berhasil diambil.',
        );
    }

    public function store(
        StorePerformanceReviewItemRequest $request,
        PerformanceReview $performanceReview
    ): JsonResponse {
        Gate::authorize('update', $performanceReview);

        $item = $this->performanceReviewItemService->create(
            $performanceReview,
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformanceReviewItemResource($item),
            message: 'Performance review item berhasil dibuat.',
            status: 201,
        );
    }

    public function show(
        PerformanceReviewItem $performanceReviewItem
    ): JsonResponse {
        $performanceReviewItem->loadMissing('review');

        Gate::authorize('view', $performanceReviewItem->review);

        $item = $this->performanceReviewItemService->getById(
            $performanceReviewItem,
        );

        return ApiResponse::success(
            data: new PerformanceReviewItemResource($item),
            message: 'Performance review item berhasil diambil.',
        );
    }

    public function update(
        UpdatePerformanceReviewItemRequest $request,
        PerformanceReviewItem $performanceReviewItem
    ): JsonResponse {
        $performanceReviewItem->loadMissing('review');

        Gate::authorize('update', $performanceReviewItem->review);

        $item = $this->performanceReviewItemService->update(
            $performanceReviewItem,
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformanceReviewItemResource($item),
            message: 'Performance review item berhasil diperbarui.',
        );
    }

    public function destroy(
        PerformanceReviewItem $performanceReviewItem
    ): JsonResponse {
        $performanceReviewItem->loadMissing('review');

        Gate::authorize('update', $performanceReviewItem->review);

        $this->performanceReviewItemService->delete(
            $performanceReviewItem,
        );

        return ApiResponse::success(
            message: 'Performance review item berhasil dihapus.',
            status: 204,
        );
    }
}
