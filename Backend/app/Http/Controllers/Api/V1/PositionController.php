<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Position\StorePositionRequest;
use App\Http\Requests\Position\UpdatePositionRequest;
use App\Http\Resources\V1\PositionResource;
use App\Models\Position;
use App\Services\PositionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PositionController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PositionService $positionService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:position.view', only: ['index', 'show']),
            new Middleware('permission:position.create', only: ['store']),
            new Middleware('permission:position.update', only: ['update']),
            new Middleware('permission:position.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1),  100,);
        $search = trim((string) $request->query('search', ''));

        $position = $this->positionService->paginate(
            perPage: $perPage,
            search: $search,
        );

        return ApiResponse::success(
            data: PositionResource::collection($position),
            message: 'Daftar position berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $position->currentPage(),
                    'last_page' => $position->lastPage(),
                    'per_page' => $position->perPage(),
                    'total' => $position->total(),
                    'from' => $position->firstItem(),
                    'to' => $position->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = $this->positionService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: PositionResource::make($position),
            message: 'Position berhasil dibuat.',
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Position $position): JsonResponse
    {
        return ApiResponse::success(
            data: PositionResource::make($position),
            message: 'Detail Position berhasil diambil.',
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        $position = $this->positionService->update(
            $position,
            $request->validated(),
        );

        return ApiResponse::success(
            data: PositionResource::make($position),
            message: 'Position berhasil diperbarui.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Position $position): JsonResponse
    {
        $this->positionService->delete($position);

        return ApiResponse::success(
            data: null,
            message: 'Position berhasil dihapus.',
        );
    }
}
