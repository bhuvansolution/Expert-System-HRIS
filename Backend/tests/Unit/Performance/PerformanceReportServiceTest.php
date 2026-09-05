<?php

namespace Tests\Unit\Performance;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\Position;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\Performance\PerformanceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerformanceReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PerformanceReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PerformanceReportService::class);
        $this->createRoles();
    }

    private function createRoles(): void
    {
        foreach (
            [
                'super-admin',
                'admin',
                'hr-admin',
                'manager',
                'employee',
            ] as $role
        ) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }
    }

    private function createUser(?string $role = null): User
    {
        $user = User::factory()->create();

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function createDepartment(
        string $code,
        string $name
    ): Department {
        return Department::create([
            'code' => $code,
            'name' => $name,
            'description' => $name . ' Department',
            'status' => 'active',
        ]);
    }

    private function createPosition(
        string $code = 'STAFF'
    ): Position {
        return Position::create([
            'code' => $code . '-' . fake()->unique()->numerify('###'),
            'name' => 'Staff',
            'description' => 'Staff Position',
            'level' => 1,
            'status' => 'active',
        ]);
    }

    private function createEmployee(
        Department $department,
        ?User $user = null
    ): Employee {
        $user ??= $this->createUser();

        $position = $this->createPosition();

        $employeeService = app(EmployeeService::class);

        return $employeeService->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-' . fake()->unique()->numerify('#####'),
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'gender' => 'male',
            'join_date' => now()->subYear()->toDateString(),
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'history_reason' => 'Initial employment',
            'history_notes' => 'Employee joined the company.',
        ]);
    }

    private function createPeriod(
        string $name = 'Performance Period'
    ): PerformancePeriod {
        return PerformancePeriod::create([
            'name' => $name . ' ' . fake()->unique()->numerify('####'),
            'start_date' => now()->subYear()->startOfYear()->toDateString(),
            'end_date' => now()->subYear()->endOfYear()->toDateString(),
            'status' => 'completed',
            'description' => 'Test performance period',
        ]);
    }

    private function createReview(
        Employee $employee,
        PerformancePeriod $period,
        User $reviewer,
        float $score = 80,
        string $status = 'approved',
        string $reviewType = 'manager'
    ): PerformanceReview {
        return PerformanceReview::create([
            'employee_id' => $employee->id,
            'performance_period_id' => $period->id,
            'reviewer_id' => $reviewer->id,
            'review_type' => $reviewType,
            'status' => $status,
            'overall_score' => $score,
            'review_date' => now()->toDateString(),
            'comments' => 'Test performance review',
        ]);
    }

    public function test_can_generate_performance_report(): void
    {
        $reviewer = $this->createUser('admin');

        $department = $this->createDepartment(
            'HR',
            'Human Resources'
        );

        $employee = $this->createEmployee($department);

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $reviewer,
            85
        );

        $result = $this->service->generate([]);

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('by_department', $result);
        $this->assertArrayHasKey('by_review_type', $result);
        $this->assertArrayHasKey('by_period', $result);
    }

    public function test_only_approved_reviews_are_included(): void
    {
        $reviewer = $this->createUser('admin');

        $department = $this->createDepartment(
            'HR',
            'Human Resources'
        );

        $employee = $this->createEmployee($department);

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $reviewer,
            90,
            'approved'
        );

        $this->createReview(
            $employee,
            $period,
            $reviewer,
            80,
            'draft'
        );

        $this->createReview(
            $employee,
            $period,
            $reviewer,
            70,
            'submitted'
        );

        $this->createReview(
            $employee,
            $period,
            $reviewer,
            60,
            'rejected'
        );

        $result = $this->service->generate([]);

        $this->assertEquals(
            1,
            $result['summary']['total_reviews']
        );

        $this->assertEquals(
            90,
            (float) $result['summary']['average_score']
        );
    }

    public function test_summary_calculates_total_reviews(): void
    {
        $reviewer = $this->createUser('admin');

        $department = $this->createDepartment(
            'HR',
            'Human Resources'
        );

        $employee1 = $this->createEmployee($department);
        $employee2 = $this->createEmployee($department);

        $period = $this->createPeriod();

        $this->createReview(
            $employee1,
            $period,
            $reviewer,
            80
        );

        $this->createReview(
            $employee2,
            $period,
            $reviewer,
            90
        );

        $result = $this->service->generate([]);

        $this->assertEquals(
            2,
            $result['summary']['total_reviews']
        );
    }

    public function test_summary_counts_unique_employees(): void
    {
        $reviewer = $this->createUser('admin');

        $department = $this->createDepartment(
            'HR',
            'Human Resources'
        );

        $employee = $this->createEmployee($department);

        $period1 = $this->createPeriod('Period A');
        $period2 = $this->createPeriod('Period B');

        $this->createReview(
            $employee,
            $period1,
            $reviewer,
            80
        );

        $this->createReview(
            $employee,
            $period2,
            $reviewer,
            90
        );

        $result = $this->service->generate([]);

        $this->assertEquals(
            2,
            $result['summary']['total_reviews']
        );

        $this->assertEquals(
            1,
            $result['summary']['total_employees']
        );
    }

    public function test_summary_calculates_average_highest_and_lowest_score(): void
    {
        $reviewer = $this->createUser('admin');

        $department = $this->createDepartment(
            'HR',
            'Human Resources'
        );

        $employee1 = $this->createEmployee($department);
        $employee2 = $this->createEmployee($department);
        $employee3 = $this->createEmployee($department);

        $period = $this->createPeriod();

        $this->createReview(
            $employee1,
            $period,
            $reviewer,
            70
        );

        $this->createReview(
            $employee2,
            $period,
            $reviewer,
            80
        );

        $this->createReview(
            $employee3,
            $period,
            $reviewer,
            90
        );

        $result = $this->service->generate([]);

        $this->assertEquals(
            80,
            (float) $result['summary']['average_score']
        );

        $this->assertEquals(
            90,
            (float) $result['summary']['highest_score']
        );

        $this->assertEquals(
            70,
            (float) $result['summary']['lowest_score']
        );
    }

    public function test_can_filter_by_period(): void
    {
        $reviewer = $this->createUser('admin');

        $department = $this->createDepartment(
            'HR',
            'Human Resources'
        );

        $employee1 = $this->createEmployee($department);
        $employee2 = $this->createEmployee($department);

        $period1 = $this->createPeriod('Period A');
        $period2 = $this->createPeriod('Period B');

        $this->createReview(
            $employee1,
            $period1,
            $reviewer,
            80
        );

        $this->createReview(
            $employee2,
            $period2,
            $reviewer,
            90
        );

        $result = $this->service->generate([
            'period_id' => $period1->id,
        ]);

        $this->assertEquals(
            1,
            $result['summary']['total_reviews']
        );

        $this->assertEquals(
            80,
            (float) $result['summary']['average_score']
        );
    }

    public function test_can_filter_by_employee(): void
    {
        $reviewer = $this->createUser('admin');

        $department = $this->createDepartment(
            'HR',
            'Human Resources'
        );

        $employee1 = $this->createEmployee($department);
        $employee2 = $this->createEmployee($department);

        $period = $this->createPeriod();

        $this->createReview(
            $employee1,
            $period,
            $reviewer,
            80
        );

        $this->createReview(
            $employee2,
            $period,
            $reviewer,
            90
        );

        $result = $this->service->generate([
            'employee_id' => $employee1->id,
        ]);

        $this->assertEquals(
            1,
            $result['summary']['total_reviews']
        );

        $this->assertEquals(
            80,
            (float) $result['summary']['average_score']
        );
    }

    public function test_can_filter_by_department(): void
    {
        $reviewer = $this->createUser('admin');

        $hr = $this->createDepartment(
            'HR',
            'Human Resources'
        );

        $it = $this->createDepartment(
            'IT',
            'Information Technology'
        );

        $hrEmployee = $this->createEmployee($hr);
        $itEmployee = $this->createEmployee($it);

        $period = $this->createPeriod();

        $this->createReview(
            $hrEmployee,
            $period,
            $reviewer,
            80
        );

        $this->createReview(
            $itEmployee,
            $period,
            $reviewer,
            90
        );

        $result = $this->service->generate([
            'department_id' => $hr->id,
        ]);

        $this->assertEquals(
            1,
            $result['summary']['total_reviews']
        );

        $this->assertEquals(
            80,
            (float) $result['summary']['average_score']
        );
    }

    public function test_can_filter_by_review_type(): void
    {
        $reviewer = $this->createUser('admin');

        $department = $this->createDepartment(
            'HR',
            'Human Resources'
        );

        $employee1 = $this->createEmployee($department);
        $employee2 = $this->createEmployee($department);

        $period = $this->createPeriod();

        $this->createReview(
            $employee1,
            $period,
            $reviewer,
            80,
            'approved',
            'manager'
        );

        $this->createReview(
            $employee2,
            $period,
            $reviewer,
            90,
            'approved',
            'self'
        );

        $result = $this->service->generate([
            'review_type' => 'manager',
        ]);

        $this->assertEquals(
            1,
            $result['summary']['total_reviews']
        );

        $this->assertEquals(
            80,
            (float) $result['summary']['average_score']
        );
    }

    public function test_groups_reviews_by_department(): void
    {
        $reviewer = $this->createUser('admin');

        $hr = $this->createDepartment(
            'HR',
            'Human Resources'
        );

        $it = $this->createDepartment(
            'IT',
            'Information Technology'
        );

        $hrEmployee = $this->createEmployee($hr);
        $itEmployee = $this->createEmployee($it);

        $period = $this->createPeriod();

        $this->createReview(
            $hrEmployee,
            $period,
            $reviewer,
            80
        );

        $this->createReview(
            $itEmployee,
            $period,
            $reviewer,
            90
        );

        $result = $this->service->generate([]);

        $this->assertCount(
            2,
            $result['by_department']
        );

        $hrReport = collect(
            $result['by_department']
        )->firstWhere(
            'department_id',
            $hr->id
        );

        $this->assertNotNull($hrReport);
        $this->assertEquals(
            'Human Resources',
            $hrReport['department_name']
        );
        $this->assertEquals(
            1,
            $hrReport['total_reviews']
        );
        $this->assertEquals(
            80,
            (float) $hrReport['average_score']
        );
    }

    public function test_groups_reviews_by_review_type(): void
    {
        $reviewer = $this->createUser('admin');

        $department = $this->createDepartment(
            'HR',
            'Human Resources'
        );

        $employee1 = $this->createEmployee($department);
        $employee2 = $this->createEmployee($department);

        $period = $this->createPeriod();

        $this->createReview(
            $employee1,
            $period,
            $reviewer,
            80,
            'approved',
            'manager'
        );

        $this->createReview(
            $employee2,
            $period,
            $reviewer,
            90,
            'approved',
            'self'
        );

        $result = $this->service->generate([]);

        $this->assertCount(
            2,
            $result['by_review_type']
        );

        $managerReport = collect(
            $result['by_review_type']
        )->firstWhere(
            'review_type',
            'manager'
        );

        $this->assertNotNull($managerReport);
        $this->assertEquals(
            1,
            $managerReport['total_reviews']
        );
        $this->assertEquals(
            80,
            (float) $managerReport['average_score']
        );
    }

    public function test_groups_reviews_by_period(): void
    {
        $reviewer = $this->createUser('admin');

        $department = $this->createDepartment(
            'HR',
            'Human Resources'
        );

        $employee1 = $this->createEmployee($department);
        $employee2 = $this->createEmployee($department);

        $period1 = $this->createPeriod('Period A');
        $period2 = $this->createPeriod('Period B');

        $this->createReview(
            $employee1,
            $period1,
            $reviewer,
            80
        );

        $this->createReview(
            $employee2,
            $period2,
            $reviewer,
            90
        );

        $result = $this->service->generate([]);

        $this->assertCount(
            2,
            $result['by_period']
        );

        $periodReport = collect(
            $result['by_period']
        )->firstWhere(
            'period_id',
            $period1->id
        );

        $this->assertNotNull($periodReport);
        $this->assertEquals(
            $period1->name,
            $periodReport['period_name']
        );
        $this->assertEquals(
            1,
            $periodReport['total_reviews']
        );
        $this->assertEquals(
            80,
            (float) $periodReport['average_score']
        );
    }
}
