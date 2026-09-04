<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\PerformanceReportRequest;
use App\Http\Resources\V1\Performance\PerformanceReportResource;
use App\Services\Performance\PerformanceReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PerformanceReportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PerformanceReportService $performanceReportService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:performance_report.view', only: ['index']),
        ];
    }


    public function index(
        PerformanceReportRequest $request
    ): JsonResponse {
        $report = $this->performanceReportService->generate(
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformanceReportResource($report),
            message: 'Performance report berhasil diambil.',
        );
    }
}
