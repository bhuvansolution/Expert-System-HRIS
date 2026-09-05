<?php

namespace Tests\Unit\Leave;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Position;
use App\Services\Leave\LeaveBalanceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeaveBalanceService $leaveBalanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leaveBalanceService = app(LeaveBalanceService::class);
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
            'code' => 'STAFF',
            'name' => 'HR Staff',
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(
        ?Department $department = null,
        ?Position $position = null,
        string $employeeNumber = 'EMP-001',
    ): Employee {
        $department ??= $this->createDepartment();
        $position ??= $this->createPosition();

        return Employee::query()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
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
        ]);
    }

    private function createLeaveType(
        string $code = 'AL',
        string $name = 'Annual Leave',
    ): LeaveType {
        return LeaveType::query()->create([
            'name' => $name,
            'code' => $code,
            'default_days' => 12,
            'description' => $name . ' description.',
            'status' => 'active',
        ]);
    }

    private function createLeaveBalance(
        Employee $employee,
        LeaveType $leaveType,
        int $year = 2026,
        float $allocatedDays = 12,
        float $usedDays = 3,
        float $remainingDays = 9,
    ): LeaveBalance {
        return LeaveBalance::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => $year,
            'allocated_days' => $allocatedDays,
            'used_days' => $usedDays,
            'remaining_days' => $remainingDays,
        ]);
    }

    public function test_it_can_paginate_leave_balances(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $result = $this->leaveBalanceService->paginate();

        $this->assertSame(1, $result->total());

        $items = $result->items();

        $this->assertCount(1, $items);
        $this->assertSame(
            $employee->id,
            $items[0]->employee_id
        );
        $this->assertSame(
            $leaveType->id,
            $items[0]->leave_type_id
        );

        $this->assertTrue(
            $items[0]->relationLoaded('employee')
        );

        $this->assertTrue(
            $items[0]->relationLoaded('leaveType')
        );
    }

    public function test_it_can_filter_leave_balances_by_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employeeOne = $this->createEmployee(
            department: $department,
            position: $position,
            employeeNumber: 'EMP-001',
        );

        $employeeTwo = $this->createEmployee(
            department: $department,
            position: $position,
            employeeNumber: 'EMP-002',
        );

        $leaveType = $this->createLeaveType();

        $this->createLeaveBalance(
            employee: $employeeOne,
            leaveType: $leaveType,
        );

        $this->createLeaveBalance(
            employee: $employeeTwo,
            leaveType: $leaveType,
        );

        $result = $this->leaveBalanceService->paginate(
            employeeId: $employeeTwo->id,
        );

        $this->assertSame(1, $result->total());

        $items = $result->items();

        $this->assertSame(
            $employeeTwo->id,
            $items[0]->employee_id
        );
    }

    public function test_it_can_filter_leave_balances_by_leave_type(): void
    {
        $employee = $this->createEmployee();

        $annualLeave = $this->createLeaveType(
            code: 'AL',
            name: 'Annual Leave',
        );

        $sickLeave = $this->createLeaveType(
            code: 'SL',
            name: 'Sick Leave',
        );

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $annualLeave,
        );

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $sickLeave,
        );

        $result = $this->leaveBalanceService->paginate(
            leaveTypeId: $sickLeave->id,
        );

        $this->assertSame(1, $result->total());

        $items = $result->items();

        $this->assertSame(
            $sickLeave->id,
            $items[0]->leave_type_id
        );
    }

    public function test_it_can_filter_leave_balances_by_year(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
            year: 2025,
        );

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: LeaveType::query()->create([
                'name' => 'Annual Leave 2026',
                'code' => 'AL26',
                'default_days' => 12,
                'description' => 'Annual leave 2026.',
                'status' => 'active',
            ]),
            year: 2026,
        );

        $result = $this->leaveBalanceService->paginate(
            year: 2026,
        );

        $this->assertSame(1, $result->total());

        $items = $result->items();

        $this->assertSame(
            2026,
            $items[0]->year
        );
    }

    public function test_it_can_get_my_balances(): void
    {
        $employee = $this->createEmployee();

        $annualLeave = $this->createLeaveType(
            code: 'AL',
            name: 'Annual Leave',
        );

        $sickLeave = $this->createLeaveType(
            code: 'SL',
            name: 'Sick Leave',
        );

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $annualLeave,
            year: 2026,
            allocatedDays: 12,
            usedDays: 3,
            remainingDays: 9,
        );

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $sickLeave,
            year: 2026,
            allocatedDays: 10,
            usedDays: 2,
            remainingDays: 8,
        );

        $result = $this->leaveBalanceService->getMyBalances(
            employee: $employee,
            year: 2026,
        );

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $this->assertSame(
            $annualLeave->id,
            $result[0]->leave_type_id
        );

        $this->assertSame(
            $sickLeave->id,
            $result[1]->leave_type_id
        );

        $this->assertTrue(
            $result[0]->relationLoaded('leaveType')
        );
    }

    public function test_it_can_get_balances_by_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employeeOne = $this->createEmployee(
            department: $department,
            position: $position,
            employeeNumber: 'EMP-001',
        );

        $employeeTwo = $this->createEmployee(
            department: $department,
            position: $position,
            employeeNumber: 'EMP-002',
        );

        $leaveType = $this->createLeaveType();

        $this->createLeaveBalance(
            employee: $employeeOne,
            leaveType: $leaveType,
        );

        $this->createLeaveBalance(
            employee: $employeeTwo,
            leaveType: $leaveType,
        );

        $result = $this->leaveBalanceService->getByEmployee(
            employee: $employeeTwo,
        );

        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        $this->assertSame(
            $employeeTwo->id,
            $result[0]->employee_id
        );

        $this->assertTrue(
            $result[0]->relationLoaded('employee')
        );

        $this->assertTrue(
            $result[0]->relationLoaded('leaveType')
        );
    }

    public function test_it_can_filter_my_balances_by_year(): void
    {
        $employee = $this->createEmployee();

        $leaveType2025 = $this->createLeaveType(
            code: 'AL25',
            name: 'Annual Leave 2025',
        );

        $leaveType2026 = $this->createLeaveType(
            code: 'AL26',
            name: 'Annual Leave 2026',
        );

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType2025,
            year: 2025,
        );

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType2026,
            year: 2026,
        );

        $result = $this->leaveBalanceService->getMyBalances(
            employee: $employee,
            year: 2026,
        );

        $this->assertCount(1, $result);

        $this->assertSame(
            2026,
            $result[0]->year
        );
    }

    public function test_it_can_find_leave_balance_by_id(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $leaveBalance = $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $result = $this->leaveBalanceService->findById(
            $leaveBalance->id
        );

        $this->assertInstanceOf(
            LeaveBalance::class,
            $result
        );

        $this->assertSame(
            $leaveBalance->id,
            $result->id
        );

        $this->assertSame(
            $employee->id,
            $result->employee_id
        );

        $this->assertSame(
            $leaveType->id,
            $result->leave_type_id
        );

        $this->assertTrue(
            $result->relationLoaded('employee')
        );

        $this->assertTrue(
            $result->relationLoaded('leaveType')
        );
    }

    public function test_it_throws_exception_when_leave_balance_is_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->leaveBalanceService->findById(999999);
    }
}
