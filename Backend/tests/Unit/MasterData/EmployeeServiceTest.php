<?php

namespace Tests\Unit\MasterData;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeService $employeeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employeeService = app(EmployeeService::class);
    }

    private function createDepartment(): Department
    {
        return Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(): Position
    {
        return Position::query()->create([
            'code' => 'HR-STAFF',
            'name' => 'HR Staff',
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function employeeData(
        Department $department,
        Position $position,
        ?int $userId = null,
        ?int $managerId = null,
        string $employeeNumber = 'EMP-001',
    ): array {
        return [
            'user_id' => $userId,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => $managerId,
            'employee_number' => $employeeNumber,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1995-01-15',
            'phone' => '081234567890',
            'address' => 'Jakarta',
            'join_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'history_reason' => 'Initial employment',
            'history_notes' => 'Employee joined the company.',
        ];
    }

    public function test_it_can_create_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $user = User::factory()->create();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                userId: $user->id,
            ),
        );

        $this->assertInstanceOf(Employee::class, $employee);

        $this->assertSame('EMP-001', $employee->employee_number);
        $this->assertSame('John', $employee->first_name);
        $this->assertSame($department->id, $employee->department_id);
        $this->assertSame($position->id, $employee->position_id);
        $this->assertSame($user->id, $employee->user_id);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'employee_number' => 'EMP-001',
            'first_name' => 'John',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('employment_histories', [
            'employee_id' => $employee->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employment_type' => 'full_time',
            'start_date' => '2026-01-01',
        ]);
    }

    public function test_it_can_create_employee_without_user(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );

        $this->assertNull($employee->user_id);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'user_id' => null,
        ]);
    }

    public function test_it_can_find_employee_by_id(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );

        $result = $this->employeeService->findById(
            $employee->id
        );

        $this->assertInstanceOf(Employee::class, $result);
        $this->assertSame($employee->id, $result->id);

        $this->assertTrue($result->relationLoaded('department'));
        $this->assertTrue($result->relationLoaded('position'));
        $this->assertTrue($result->relationLoaded('employmentHistories'));
    }

    public function test_it_throws_exception_when_employee_is_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->employeeService->findById(999999);
    }

    public function test_it_can_search_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                employeeNumber: 'EMP-001',
            ),
        );

        $this->employeeService->create(
            array_merge(
                $this->employeeData(
                    department: $department,
                    position: $position,
                    employeeNumber: 'EMP-002',
                ),
                [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                ],
            ),
        );

        $result = $this->employeeService->paginate(
            perPage: 15,
            search: 'Jane',
        );

        $this->assertSame(1, $result->total());
        $this->assertSame(
            'Jane',
            $result->items()[0]->first_name
        );
    }

    public function test_it_can_filter_employee_by_department(): void
    {
        $hrDepartment = $this->createDepartment();

        $itDepartment = Department::query()->create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'description' => 'IT Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $position = $this->createPosition();

        $this->employeeService->create(
            $this->employeeData(
                department: $hrDepartment,
                position: $position,
                employeeNumber: 'EMP-001',
            ),
        );

        $this->employeeService->create(
            $this->employeeData(
                department: $itDepartment,
                position: $position,
                employeeNumber: 'EMP-002',
            ),
        );

        $result = $this->employeeService->paginate(
            perPage: 15,
            departmentId: $itDepartment->id,
        );

        $this->assertSame(1, $result->total());

        $this->assertSame(
            'EMP-002',
            $result->items()[0]->employee_number
        );
    }

    public function test_it_can_filter_employee_by_position(): void
    {
        $department = $this->createDepartment();

        $staffPosition = $this->createPosition();

        $managerPosition = Position::query()->create([
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'description' => 'HR Manager Position',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $staffPosition,
                employeeNumber: 'EMP-001',
            ),
        );

        $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $managerPosition,
                employeeNumber: 'EMP-002',
            ),
        );

        $result = $this->employeeService->paginate(
            perPage: 15,
            positionId: $managerPosition->id,
        );

        $this->assertSame(1, $result->total());

        $this->assertSame(
            'EMP-002',
            $result->items()[0]->employee_number
        );
    }

    public function test_it_can_update_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );

        $result = $this->employeeService->update(
            $employee,
            [
                'first_name' => 'Jonathan',
                'last_name' => 'Updated',
                'employment_status' => 'inactive',
            ],
        );

        $this->assertSame(
            'Jonathan',
            $result->first_name
        );

        $this->assertSame(
            'Updated',
            $result->last_name
        );

        $this->assertSame(
            'inactive',
            $result->employment_status
        );

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'Jonathan',
            'last_name' => 'Updated',
            'employment_status' => 'inactive',
        ]);
    }

    public function test_it_can_delete_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );

        $this->employeeService->delete($employee);

        $this->assertSoftDeleted('employees', [
            'id' => $employee->id,
        ]);
    }
}
