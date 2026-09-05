<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $attendanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attendanceService = app(AttendanceService::class);
    }

    private function createDepartment(
        string $code = 'HR',
    ): Department {
        return Department::query()->create([
            'code' => $code,
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(
        string $code = 'STAFF',
    ): Position {
        return Position::query()->create([
            'code' => $code,
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

    private function createAttendance(
        Employee $employee,
        string $date = '2026-09-01',
        string $status = 'present',
        int $lateMinutes = 0,
        int $workingMinutes = 480,
    ): Attendance {
        return Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'clock_in' => $date . ' 08:00:00',
            'clock_out' => $date . ' 16:00:00',
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'working_minutes' => $workingMinutes,
            'notes' => null,
        ]);
    }

    public function test_it_can_clock_in_employee(): void
    {
        $employee = $this->createEmployee();

        Carbon::setTestNow(
            Carbon::parse('2026-09-04 08:00:00')
        );

        $attendance = $this->attendanceService->clockIn($employee);

        $this->assertInstanceOf(
            Attendance::class,
            $attendance
        );

        $this->assertEquals(
            $employee->id,
            $attendance->employee_id
        );

        $this->assertEquals(
            '2026-09-04',
            $attendance->attendance_date->toDateString()
        );

        $this->assertEquals(
            'present',
            $attendance->status
        );

        $this->assertEquals(
            0,
            $attendance->late_minutes
        );

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-04 00:00:00',
            'status' => 'present',
        ]);

        Carbon::setTestNow();
    }

    public function test_it_marks_employee_as_late_when_clocking_in_after_work_start(): void
    {
        $employee = $this->createEmployee();

        Carbon::setTestNow(
            Carbon::parse('2026-09-04 08:30:00')
        );

        $attendance = $this->attendanceService->clockIn($employee);

        $this->assertEquals(
            'late',
            $attendance->status
        );

        $this->assertEquals(
            30,
            $attendance->late_minutes
        );

        Carbon::setTestNow();
    }

    public function test_it_cannot_clock_in_twice_on_same_day(): void
    {
        $employee = $this->createEmployee();

        Carbon::setTestNow(
            Carbon::parse('2026-09-04 08:00:00')
        );

        $this->attendanceService->clockIn($employee);

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Anda sudah melakukan clock in hari ini.'
        );

        $this->attendanceService->clockIn($employee);

        Carbon::setTestNow();
    }

    public function test_it_can_clock_out_employee(): void
    {
        $employee = $this->createEmployee();

        Carbon::setTestNow(
            Carbon::parse('2026-09-04 08:00:00')
        );

        $this->attendanceService->clockIn($employee);

        Carbon::setTestNow(
            Carbon::parse('2026-09-04 16:00:00')
        );

        $attendance = $this->attendanceService->clockOut($employee);

        $this->assertNotNull(
            $attendance->clock_out
        );

        $this->assertEquals(
            480,
            $attendance->working_minutes
        );

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-04 00:00:00',
            'working_minutes' => 480,
        ]);

        Carbon::setTestNow();
    }

    public function test_it_cannot_clock_out_without_clock_in(): void
    {
        $employee = $this->createEmployee();

        Carbon::setTestNow(
            Carbon::parse('2026-09-04 16:00:00')
        );

        $this->expectException(
            ModelNotFoundException::class
        );

        $this->expectExceptionMessage(
            'Attendance hari ini tidak ditemukan.'
        );

        $this->attendanceService->clockOut($employee);

        Carbon::setTestNow();
    }

    public function test_it_cannot_clock_out_twice(): void
    {
        $employee = $this->createEmployee();

        Carbon::setTestNow(
            Carbon::parse('2026-09-04 08:00:00')
        );

        $this->attendanceService->clockIn($employee);

        Carbon::setTestNow(
            Carbon::parse('2026-09-04 16:00:00')
        );

        $this->attendanceService->clockOut($employee);

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Anda sudah melakukan clock out hari ini.'
        );

        $this->attendanceService->clockOut($employee);

        Carbon::setTestNow();
    }

    public function test_it_can_get_all_attendance_for_employee(): void
    {
        $employee = $this->createEmployee();

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-01',
        );

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-02',
        );

        $result = $this->attendanceService->getAll(
            filters: [],
            employee: $employee,
            viewAll: false,
        );

        $this->assertCount(
            2,
            $result->items()
        );
    }

    public function test_it_can_get_all_attendance_when_view_all_is_enabled(): void
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

        $this->createAttendance(
            employee: $employeeOne,
            date: '2026-09-01',
        );

        $this->createAttendance(
            employee: $employeeTwo,
            date: '2026-09-01',
        );

        $result = $this->attendanceService->getAll(
            filters: [],
            employee: null,
            viewAll: true,
        );

        $this->assertCount(
            2,
            $result->items()
        );
    }

    public function test_it_filters_attendance_by_date_range(): void
    {
        $employee = $this->createEmployee();

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-01',
        );

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-05',
        );

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-10',
        );

        $result = $this->attendanceService->getAll(
            filters: [
                'start_date' => '2026-09-02',
                'end_date' => '2026-09-06',
            ],
            employee: $employee,
            viewAll: false,
        );

        $this->assertCount(
            1,
            $result->items()
        );
    }

    public function test_it_filters_attendance_by_status(): void
    {
        $employee = $this->createEmployee();

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-01',
            status: 'present',
        );

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-02',
            status: 'late',
            lateMinutes: 30,
        );

        $result = $this->attendanceService->getAll(
            filters: [
                'status' => 'late',
            ],
            employee: $employee,
            viewAll: false,
        );

        $this->assertCount(
            1,
            $result->items()
        );

        $this->assertEquals(
            'late',
            $result->items()[0]->status
        );
    }

    public function test_employee_cannot_get_attendance_of_another_employee(): void
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

        $attendance = $this->createAttendance(
            employee: $employeeTwo,
        );

        $this->expectException(
            ModelNotFoundException::class
        );

        $this->attendanceService->getById(
            id: $attendance->id,
            employee: $employeeOne,
            viewAll: false,
        );
    }

    public function test_it_can_get_attendance_by_id(): void
    {
        $employee = $this->createEmployee();

        $attendance = $this->createAttendance(
            employee: $employee,
        );

        $result = $this->attendanceService->getById(
            id: $attendance->id,
            employee: $employee,
            viewAll: false,
        );

        $this->assertEquals(
            $attendance->id,
            $result->id
        );

        $this->assertEquals(
            $employee->id,
            $result->employee_id
        );
    }

    public function test_it_can_get_attendance_recap(): void
    {
        $employee = $this->createEmployee();

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-01',
            status: 'present',
            lateMinutes: 0,
            workingMinutes: 480,
        );

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-02',
            status: 'late',
            lateMinutes: 30,
            workingMinutes: 450,
        );

        $result = $this->attendanceService->getRecap(
            filters: [],
            employee: $employee,
            viewAll: false,
        );

        $this->assertEquals(
            2,
            $result['total_days']
        );

        $this->assertEquals(
            1,
            $result['present']
        );

        $this->assertEquals(
            1,
            $result['late']
        );

        $this->assertEquals(
            0,
            $result['absent']
        );

        $this->assertEquals(
            30,
            $result['total_late_minutes']
        );

        $this->assertEquals(
            930,
            $result['total_working_minutes']
        );
    }

    public function test_it_can_get_attendance_report(): void
    {
        $employee = $this->createEmployee();

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-01',
            status: 'present',
        );

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-02',
            status: 'late',
            lateMinutes: 20,
        );

        $result = $this->attendanceService->getReport([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $this->assertCount(
            1,
            $result
        );

        $report = $result[0];

        $this->assertEquals(
            $employee->id,
            $report['employee_id']
        );

        $this->assertEquals(
            'EMP-001',
            $report['employee_number']
        );

        $this->assertEquals(
            'John Doe',
            $report['employee_name']
        );

        $this->assertEquals(
            1,
            $report['present']
        );

        $this->assertEquals(
            1,
            $report['late']
        );

        $this->assertEquals(
            20,
            $report['total_late_minutes']
        );

        $this->assertEquals(
            960,
            $report['total_working_minutes']
        );
    }

    public function test_it_can_filter_attendance_report_by_employee(): void
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

        $this->createAttendance(
            employee: $employeeOne,
            date: '2026-09-01',
        );

        $this->createAttendance(
            employee: $employeeTwo,
            date: '2026-09-01',
        );

        $result = $this->attendanceService->getReport([
            'employee_id' => $employeeOne->id,
        ]);

        $this->assertCount(
            1,
            $result
        );

        $this->assertEquals(
            $employeeOne->id,
            $result[0]['employee_id']
        );
    }

    public function test_it_can_filter_attendance_report_by_status(): void
    {
        $employee = $this->createEmployee();

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-01',
            status: 'present',
        );

        $this->createAttendance(
            employee: $employee,
            date: '2026-09-02',
            status: 'late',
            lateMinutes: 30,
        );

        $result = $this->attendanceService->getReport([
            'status' => 'late',
        ]);

        $this->assertCount(
            1,
            $result
        );

        $this->assertEquals(
            1,
            $result[0]['late']
        );

        $this->assertEquals(
            0,
            $result[0]['present']
        );
    }
}
