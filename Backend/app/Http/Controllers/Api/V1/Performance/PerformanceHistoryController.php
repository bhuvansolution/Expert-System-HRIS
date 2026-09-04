<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Performance\PerformanceHistoryResource;
use App\Models\Employee;
use App\Services\Performance\PerformanceHistoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PerformanceHistoryController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PerformanceHistoryService $performanceHistoryService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:performance_review.view', only: ['index', 'employee']),
        ];
    }

    public function index(
        Request $request
    ): JsonResponse {
        $history = $this->performanceHistoryService->getHistory(
            $request->user(),
        );

        return ApiResponse::success(
            data: PerformanceHistoryResource::collection($history),
            message: 'Performance history berhasil diambil.',
        );
    }

    public function employee(
        Request $request,
        Employee $employee
    ): JsonResponse {
        $history = $this->performanceHistoryService->getHistory(
            $request->user(),
            $employee,
        );

        return ApiResponse::success(
            data: PerformanceHistoryResource::collection($history),
            message: 'Performance history employee berhasil diambil.',
        );
    }
}
