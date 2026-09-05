<?php

namespace Tests\Unit\Leave;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use App\Services\Leave\LeaveRequestService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class LeaveRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeaveRequestService $leaveRequestService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leaveRequestService = app(LeaveRequestService::class);
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
        string $employeeNumber = 'EMP-001',
        ?Department $department = null,
        ?Position $position = null,
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
        string $status = 'active',
    ): LeaveType {
        return LeaveType::query()->create([
            'name' => $name,
            'code' => $code,
            'default_days' => 12,
            'description' => $name . ' description.',
            'status' => $status,
        ]);
    }

    private function createLeaveBalance(
        Employee $employee,
        LeaveType $leaveType,
        int $year = 2026,
        float $allocatedDays = 12,
        float $usedDays = 0,
        float $remainingDays = 12,
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

    private function createLeaveRequest(
        Employee $employee,
        LeaveType $leaveType,
        string $startDate = '2026-03-10',
        string $endDate = '2026-03-12',
        string $status = 'pending',
    ): LeaveRequest {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        return LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $start,
            'end_date' => $end,
            'total_days' => $start->diffInDays($end) + 1,
            'reason' => 'Personal leave.',
            'status' => $status,
        ]);
    }

    public function test_it_can_paginate_leave_requests(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
        );

        $result = $this->leaveRequestService->paginate();

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

        $this->assertTrue(
            $items[0]->relationLoaded('approvedBy')
        );
    }

    public function test_it_can_filter_leave_requests(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employeeOne = $this->createEmployee(
            employeeNumber: 'EMP-001',
            department: $department,
            position: $position,
        );

        $employeeTwo = $this->createEmployee(
            employeeNumber: 'EMP-002',
            department: $department,
            position: $position,
        );

        $annualLeave = $this->createLeaveType(
            code: 'AL',
            name: 'Annual Leave',
        );

        $sickLeave = $this->createLeaveType(
            code: 'SL',
            name: 'Sick Leave',
        );

        $this->createLeaveRequest(
            employee: $employeeOne,
            leaveType: $annualLeave,
            startDate: '2026-03-10',
            endDate: '2026-03-12',
            status: 'pending',
        );

        $this->createLeaveRequest(
            employee: $employeeTwo,
            leaveType: $sickLeave,
            startDate: '2026-04-10',
            endDate: '2026-04-11',
            status: 'approved',
        );

        $result = $this->leaveRequestService->paginate(
            employeeId: $employeeTwo->id,
            leaveTypeId: $sickLeave->id,
            year: 2026,
            status: 'approved',
        );

        $this->assertSame(1, $result->total());

        $items = $result->items();

        $this->assertSame(
            $employeeTwo->id,
            $items[0]->employee_id
        );

        $this->assertSame(
            $sickLeave->id,
            $items[0]->leave_type_id
        );

        $this->assertSame(
            'approved',
            $items[0]->status
        );
    }

    public function test_it_can_get_my_requests(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employeeOne = $this->createEmployee(
            employeeNumber: 'EMP-001',
            department: $department,
            position: $position,
        );

        $employeeTwo = $this->createEmployee(
            employeeNumber: 'EMP-002',
            department: $department,
            position: $position,
        );

        $leaveType = $this->createLeaveType();

        $this->createLeaveRequest(
            employee: $employeeOne,
            leaveType: $leaveType,
            startDate: '2026-03-10',
            endDate: '2026-03-12',
        );

        $this->createLeaveRequest(
            employee: $employeeTwo,
            leaveType: $leaveType,
            startDate: '2026-04-10',
            endDate: '2026-04-12',
        );

        $result = $this->leaveRequestService->getMyRequests(
            employee: $employeeOne,
        );

        $this->assertSame(1, $result->total());

        $items = $result->items();

        $this->assertSame(
            $employeeOne->id,
            $items[0]->employee_id
        );

        $this->assertTrue(
            $items[0]->relationLoaded('leaveType')
        );

        $this->assertTrue(
            $items[0]->relationLoaded('approvedBy')
        );
    }

    public function test_it_can_filter_my_requests_by_year_and_status(): void
    {
        $employee = $this->createEmployee();

        $leaveType = $this->createLeaveType();

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            startDate: '2025-03-10',
            endDate: '2025-03-12',
            status: 'pending',
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            startDate: '2026-04-10',
            endDate: '2026-04-12',
            status: 'approved',
        );

        $result = $this->leaveRequestService->getMyRequests(
            employee: $employee,
            status: 'approved',
            year: 2026,
        );

        $this->assertSame(1, $result->total());

        $items = $result->items();

        $this->assertSame(
            'approved',
            $items[0]->status
        );

        $this->assertSame(
            2026,
            $items[0]->start_date->year
        );
    }

    public function test_it_can_find_leave_request_by_id(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
        );

        $result = $this->leaveRequestService->findById(
            $leaveRequest->id
        );

        $this->assertInstanceOf(
            LeaveRequest::class,
            $result
        );

        $this->assertSame(
            $leaveRequest->id,
            $result->id
        );

        $this->assertTrue(
            $result->relationLoaded('employee')
        );

        $this->assertTrue(
            $result->relationLoaded('leaveType')
        );

        $this->assertTrue(
            $result->relationLoaded('approvedBy')
        );
    }

    public function test_it_throws_exception_when_leave_request_is_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->leaveRequestService->findById(999999);
    }

    public function test_it_can_create_leave_request(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $leaveRequest = $this->leaveRequestService->create(
            employee: $employee,
            data: [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-03-10',
                'end_date' => '2026-03-12',
                'reason' => 'Family event.',
            ],
        );

        $this->assertInstanceOf(
            LeaveRequest::class,
            $leaveRequest
        );

        $this->assertSame(
            $employee->id,
            $leaveRequest->employee_id
        );

        $this->assertSame(
            $leaveType->id,
            $leaveRequest->leave_type_id
        );

        $this->assertSame(
            'pending',
            $leaveRequest->status
        );

        $this->assertSame(
            3.0,
            (float) $leaveRequest->total_days
        );

        $this->assertSame(
            'Family event.',
            $leaveRequest->reason
        );

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending',
            'total_days' => 3,
        ]);
    }

    public function test_it_rejects_create_when_leave_type_is_inactive(): void
    {
        $employee = $this->createEmployee();

        $leaveType = $this->createLeaveType(
            status: 'inactive',
        );

        $this->expectException(ModelNotFoundException::class);

        $this->leaveRequestService->create(
            employee: $employee,
            data: [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-03-10',
                'end_date' => '2026-03-12',
                'reason' => 'Family event.',
            ],
        );
    }

    public function test_it_rejects_create_when_start_date_is_after_end_date(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Tanggal mulai cuti tidak boleh lebih besar dari tanggal selesai.'
        );

        $this->leaveRequestService->create(
            employee: $employee,
            data: [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-03-15',
                'end_date' => '2026-03-10',
                'reason' => 'Invalid date.',
            ],
        );
    }

    public function test_it_rejects_create_when_leave_crosses_year(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Pengajuan cuti tidak boleh melewati pergantian tahun.'
        );

        $this->leaveRequestService->create(
            employee: $employee,
            data: [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-12-30',
                'end_date' => '2027-01-02',
                'reason' => 'New year leave.',
            ],
        );
    }

    public function test_it_calculates_total_days_inclusively(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $leaveRequest = $this->leaveRequestService->create(
            employee: $employee,
            data: [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-05-10',
                'end_date' => '2026-05-10',
                'reason' => 'One day leave.',
            ],
        );

        $this->assertSame(
            1.0,
            (float) $leaveRequest->total_days
        );
    }

    public function test_it_rejects_overlapping_pending_request(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            startDate: '2026-03-10',
            endDate: '2026-03-12',
            status: 'pending',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Pengajuan cuti bertabrakan dengan pengajuan cuti yang sudah ada.'
        );

        $this->leaveRequestService->create(
            employee: $employee,
            data: [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-03-12',
                'end_date' => '2026-03-14',
                'reason' => 'Overlapping leave.',
            ],
        );
    }

    public function test_it_rejects_overlapping_approved_request(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            startDate: '2026-03-10',
            endDate: '2026-03-12',
            status: 'approved',
        );

        $this->expectException(RuntimeException::class);

        $this->leaveRequestService->create(
            employee: $employee,
            data: [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-03-11',
                'end_date' => '2026-03-13',
                'reason' => 'Overlapping leave.',
            ],
        );
    }

    public function test_it_allows_create_when_existing_request_is_rejected(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            startDate: '2026-03-10',
            endDate: '2026-03-12',
            status: 'rejected',
        );

        $leaveRequest = $this->leaveRequestService->create(
            employee: $employee,
            data: [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-03-11',
                'end_date' => '2026-03-13',
                'reason' => 'New leave request.',
            ],
        );

        $this->assertSame(
            'pending',
            $leaveRequest->status
        );
    }

    public function test_it_can_approve_leave_request(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();
        $approver = User::factory()->create();

        $leaveBalance = $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
            year: 2026,
            allocatedDays: 12,
            usedDays: 2,
            remainingDays: 10,
        );

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            startDate: '2026-03-10',
            endDate: '2026-03-12',
            status: 'pending',
        );

        $result = $this->leaveRequestService->approve(
            leaveRequest: $leaveRequest,
            approvedBy: $approver,
        );

        $this->assertSame(
            'approved',
            $result->status
        );

        $this->assertSame(
            $approver->id,
            $result->approved_by
        );

        $this->assertNotNull(
            $result->approved_at
        );

        $this->assertNull(
            $result->rejection_reason
        );

        $this->assertDatabaseHas('leave_balances', [
            'id' => $leaveBalance->id,
            'used_days' => 5,
            'remaining_days' => 7,
        ]);
    }

    public function test_it_rejects_approval_when_leave_balance_does_not_exist(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();
        $approver = User::factory()->create();

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Leave balance untuk employee dan leave type tersebut tidak ditemukan.'
        );

        $this->leaveRequestService->approve(
            leaveRequest: $leaveRequest,
            approvedBy: $approver,
        );
    }

    public function test_it_rejects_approval_when_leave_balance_is_insufficient(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();
        $approver = User::factory()->create();

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
            year: 2026,
            allocatedDays: 2,
            usedDays: 0,
            remainingDays: 2,
        );

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            startDate: '2026-03-10',
            endDate: '2026-03-12',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Saldo cuti tidak mencukupi untuk pengajuan ini.'
        );

        $this->leaveRequestService->approve(
            leaveRequest: $leaveRequest,
            approvedBy: $approver,
        );
    }

    public function test_it_rejects_approval_when_request_is_not_pending(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();
        $approver = User::factory()->create();

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'rejected',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Leave request hanya dapat disetujui ketika status masih pending.'
        );

        $this->leaveRequestService->approve(
            leaveRequest: $leaveRequest,
            approvedBy: $approver,
        );
    }

    public function test_it_can_reject_leave_request(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();
        $rejector = User::factory()->create();

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
        );

        $result = $this->leaveRequestService->reject(
            leaveRequest: $leaveRequest,
            rejectedBy: $rejector,
            rejectionReason: 'Project deadline.',
        );

        $this->assertSame(
            'rejected',
            $result->status
        );

        $this->assertSame(
            $rejector->id,
            $result->approved_by
        );

        $this->assertNull(
            $result->approved_at
        );

        $this->assertSame(
            'Project deadline.',
            $result->rejection_reason
        );

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'rejected',
            'approved_by' => $rejector->id,
            'rejection_reason' => 'Project deadline.',
        ]);
    }

    public function test_it_rejects_reject_action_when_request_is_not_pending(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();
        $rejector = User::factory()->create();

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'approved',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Leave request hanya dapat ditolak ketika status masih pending.'
        );

        $this->leaveRequestService->reject(
            leaveRequest: $leaveRequest,
            rejectedBy: $rejector,
            rejectionReason: 'Not approved.',
        );
    }

    public function test_it_can_cancel_own_pending_leave_request(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'pending',
        );

        $result = $this->leaveRequestService->cancel(
            leaveRequest: $leaveRequest,
            employee: $employee,
        );

        $this->assertSame(
            'cancelled',
            $result->status
        );

        $this->assertNull(
            $result->approved_by
        );

        $this->assertNull(
            $result->approved_at
        );

        $this->assertNull(
            $result->rejection_reason
        );

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_it_cannot_cancel_another_employee_leave_request(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $owner = $this->createEmployee(
            employeeNumber: 'EMP-001',
            department: $department,
            position: $position,
        );

        $anotherEmployee = $this->createEmployee(
            employeeNumber: 'EMP-002',
            department: $department,
            position: $position,
        );

        $leaveType = $this->createLeaveType();

        $leaveRequest = $this->createLeaveRequest(
            employee: $owner,
            leaveType: $leaveType,
            status: 'pending',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Anda tidak dapat membatalkan pengajuan cuti milik employee lain.'
        );

        $this->leaveRequestService->cancel(
            leaveRequest: $leaveRequest,
            employee: $anotherEmployee,
        );
    }

    public function test_it_cannot_cancel_non_pending_leave_request(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'approved',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Leave request hanya dapat dibatalkan ketika status masih pending.'
        );

        $this->leaveRequestService->cancel(
            leaveRequest: $leaveRequest,
            employee: $employee,
        );
    }
}
