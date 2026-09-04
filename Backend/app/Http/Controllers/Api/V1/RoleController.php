<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\V1\RoleResource;
use App\Services\RoleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RoleController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:role.view', only: ['index', 'show']),
            new Middleware('permission:role.create', only: ['store']),
            new Middleware('permission:role.update', only: ['update']),
            new Middleware('permission:role.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $roles = $this->roleService->getAll();

        return ApiResponse::success(
            data: RoleResource::collection($roles),
            message: 'Daftar role berhasil diambil.',
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->create(
            $request->validated('name'),
            $request->validated('permissions', []),
        );

        return ApiResponse::success(
            data: RoleResource::make($role),
            message: 'Role berhasil dibuat.',
        );
    }


    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return ApiResponse::success(
            data: RoleResource::make($role),
            message: 'Detail role berhasil diambil.',
        );
    }

    public function update(
        UpdateRoleRequest $request,
        Role $role
    ): JsonResponse {
        $role = $this->roleService->update(
            $role,
            $request->validated('name'),
            $request->validated('permissions', []),
        );

        return ApiResponse::success(
            data: RoleResource::make($role),
            message: 'Role berhasil diperbarui.',
        );
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->roleService->delete($role);

        return ApiResponse::success(
            data: null,
            message: 'Role berhasil dihapus.',
        );
    }
}
