<?php

namespace Tests\Unit\MasterData;

use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

class DepartmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private DepartmentService $departmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->departmentService = app(DepartmentService::class);
    }

    public function test_it_can_create_department(): void
    {
        $department = $this->departmentService->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'Human Resources Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Department::class, $department);

        $this->assertSame('HR', $department->code);
        $this->assertSame('Human Resources', $department->name);
        $this->assertTrue($department->is_active);

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'code' => 'HR',
            'name' => 'Human Resources',
            'status' => 'active',
        ]);
    }

    public function test_it_can_find_department_by_id(): void
    {
        $department = Department::query()->create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'description' => 'IT Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $result = $this->departmentService->findById(
            $department->id
        );

        $this->assertInstanceOf(Department::class, $result);
        $this->assertSame($department->id, $result->id);
    }

    public function test_it_throws_exception_when_department_is_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->departmentService->findById(999999);
    }

    public function test_it_can_search_department(): void
    {
        Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'Human Resources Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        Department::query()->create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'description' => 'Technology Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $result = $this->departmentService->paginate(
            perPage: 15,
            search: 'Human',
        );

        $this->assertSame(1, $result->total());

        $this->assertSame(
            'Human Resources',
            $result->items()[0]->name
        );
    }

    public function test_it_can_update_department(): void
    {
        $department = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'Old description',
            'status' => 'active',
            'is_active' => true,
        ]);

        $result = $this->departmentService->update(
            $department,
            [
                'name' => 'People & Culture',
                'description' => 'Updated description',
                'status' => 'inactive',
                'is_active' => false,
            ],
        );

        $this->assertSame(
            'People & Culture',
            $result->name
        );

        $this->assertSame(
            'Updated description',
            $result->description
        );

        $this->assertSame('inactive', $result->status);
        $this->assertFalse($result->is_active);

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'People & Culture',
            'status' => 'inactive',
            'is_active' => false,
        ]);
    }

    public function test_it_can_delete_department(): void
    {
        $department = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->departmentService->delete($department);

        $this->assertSoftDeleted('departments', [
            'id' => $department->id,
        ]);
    }
}
