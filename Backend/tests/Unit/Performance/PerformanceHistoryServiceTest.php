<?php

namespace Tests\Unit\Performance;


use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\Position;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\Performance\PerformanceHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerformanceHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PerformanceHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PerformanceHistoryService::class);

        $this->createRoles();
    }

    private function createRoles(): void
    {
        $roles = [
            'super-admin',
            'admin',
            'hr-admin',
            'manager',
            'employee',
        ];

        foreach ($roles as $role) {
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

    private function createEmployee(
        ?User $user = null,
        ?Employee $manager = null
    ): Employee {
        $user ??= $this->createUser();

        $department = Department::firstOrCreate(
            ['code' => 'HR'],
            [
                'name' => 'Human Resources',
                'description' => 'HR Department',
                'status' => 'active',
            ]
        );

        $position = Position::firstOrCreate(
            ['code' => 'STAFF'],
            [
                'name' => 'Staff',
                'description' => 'Staff Position',
                'level' => 1,
                'status' => 'active',
            ]
        );

        $employeeService = app(EmployeeService::class);

        $employeeData = [
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
        ];

        if ($manager) {
            $employeeData['manager_id'] = $manager->id;
        }

        return $employeeService->create($employeeData);
    }

    private function createPeriod(): PerformancePeriod
    {
        return PerformancePeriod::create([
            'name' => 'Performance Period ' . fake()->unique()->numerify('####'),
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
        string $status = 'approved',
        ?string $reviewDate = null
    ): PerformanceReview {
        return PerformanceReview::create([
            'employee_id' => $employee->id,
            'performance_period_id' => $period->id,
            'reviewer_id' => $reviewer->id,
            'review_type' => 'manager',
            'status' => $status,
            'overall_score' => 85,
            'review_date' => $reviewDate ?? now()->subMonths(3)->toDateString(),
            'comments' => 'Test performance review',
        ]);
    }


    public function test_super_admin_can_view_all_approved_histories(): void
    {
        $user = $this->createUser('super-admin');

        $employee1 = $this->createEmployee();
        $employee2 = $this->createEmployee();

        $period = $this->createPeriod();

        $this->createReview($employee1, $period, $user);
        $this->createReview($employee2, $period, $user);

        $result = $this->service->getHistory($user);

        $this->assertCount(2, $result);
        $this->assertTrue(
            $result->every(fn($review) => $review->status === 'approved')
        );
    }


    public function test_admin_can_view_all_approved_histories(): void
    {
        $user = $this->createUser('admin');

        $employee1 = $this->createEmployee();
        $employee2 = $this->createEmployee();

        $period = $this->createPeriod();

        $this->createReview($employee1, $period, $user);
        $this->createReview($employee2, $period, $user);

        $result = $this->service->getHistory($user);

        $this->assertCount(2, $result);
    }


    public function test_hr_admin_can_view_all_approved_histories(): void
    {
        $user = $this->createUser('hr-admin');

        $employee1 = $this->createEmployee();
        $employee2 = $this->createEmployee();

        $period = $this->createPeriod();

        $this->createReview($employee1, $period, $user);
        $this->createReview($employee2, $period, $user);

        $result = $this->service->getHistory($user);

        $this->assertCount(2, $result);
    }


    public function test_admin_can_filter_history_by_employee(): void
    {
        $user = $this->createUser('admin');

        $employee1 = $this->createEmployee();
        $employee2 = $this->createEmployee();

        $period = $this->createPeriod();

        $review1 = $this->createReview($employee1, $period, $user);
        $this->createReview($employee2, $period, $user);

        $result = $this->service->getHistory($user, $employee1);

        $this->assertCount(1, $result);
        $this->assertEquals($review1->id, $result->first()->id);
        $this->assertEquals($employee1->id, $result->first()->employee_id);
    }


    public function test_manager_can_view_direct_report_history(): void
    {
        $managerUser = $this->createUser('manager');

        $manager = $this->createEmployee($managerUser);

        $employee = $this->createEmployee(
            $this->createUser(),
            $manager
        );

        $otherEmployee = $this->createEmployee();

        $period = $this->createPeriod();

        $this->createReview($employee, $period, $managerUser);
        $this->createReview($otherEmployee, $period, $managerUser);

        $result = $this->service->getHistory($managerUser);

        $this->assertCount(1, $result);
        $this->assertEquals(
            $employee->id,
            $result->first()->employee_id
        );
    }


    public function test_manager_cannot_view_non_direct_report_history(): void
    {
        $managerUser = $this->createUser('manager');

        $manager = $this->createEmployee($managerUser);

        $otherEmployee = $this->createEmployee();

        $period = $this->createPeriod();

        $this->createReview($otherEmployee, $period, $managerUser);

        $result = $this->service->getHistory(
            $managerUser,
            $otherEmployee
        );

        $this->assertCount(0, $result);
    }


    public function test_manager_can_filter_direct_report_history(): void
    {
        $managerUser = $this->createUser('manager');

        $manager = $this->createEmployee($managerUser);

        $employee1 = $this->createEmployee(
            $this->createUser(),
            $manager
        );

        $employee2 = $this->createEmployee(
            $this->createUser(),
            $manager
        );

        $period = $this->createPeriod();

        $review1 = $this->createReview(
            $employee1,
            $period,
            $managerUser
        );

        $this->createReview(
            $employee2,
            $period,
            $managerUser
        );

        $result = $this->service->getHistory(
            $managerUser,
            $employee1
        );

        $this->assertCount(1, $result);
        $this->assertEquals(
            $review1->id,
            $result->first()->id
        );
    }


    public function test_employee_can_view_own_history(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee($employeeUser);
        $otherEmployee = $this->createEmployee();

        $period = $this->createPeriod();

        $reviewer = $this->createUser('manager');

        $review = $this->createReview(
            $employee,
            $period,
            $reviewer
        );

        $this->createReview(
            $otherEmployee,
            $period,
            $reviewer
        );

        $result = $this->service->getHistory($employeeUser);

        $this->assertCount(1, $result);
        $this->assertEquals(
            $review->id,
            $result->first()->id
        );
    }


    public function test_employee_cannot_view_other_employee_history(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee($employeeUser);
        $otherEmployee = $this->createEmployee();

        $period = $this->createPeriod();

        $reviewer = $this->createUser('manager');

        $this->createReview(
            $otherEmployee,
            $period,
            $reviewer
        );

        try {
            $this->service->getHistory(
                $employeeUser,
                $otherEmployee
            );

            $this->fail('Expected HTTP 403 exception was not thrown.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertEquals(403, $exception->getStatusCode());
        }
    }


    public function test_user_without_role_gets_empty_collection(): void
    {
        $user = $this->createUser();

        $employee = $this->createEmployee();

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $user
        );

        $result = $this->service->getHistory($user);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }


    public function test_draft_review_is_not_in_history(): void
    {
        $user = $this->createUser('admin');

        $employee = $this->createEmployee();

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $user,
            'draft'
        );

        $result = $this->service->getHistory($user);

        $this->assertCount(0, $result);
    }


    public function test_submitted_review_is_not_in_history(): void
    {
        $user = $this->createUser('admin');

        $employee = $this->createEmployee();

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $user,
            'submitted'
        );

        $result = $this->service->getHistory($user);

        $this->assertCount(0, $result);
    }


    public function test_rejected_review_is_not_in_history(): void
    {
        $user = $this->createUser('admin');

        $employee = $this->createEmployee();

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $user,
            'rejected'
        );

        $result = $this->service->getHistory($user);

        $this->assertCount(0, $result);
    }


    public function test_history_is_ordered_by_latest_review_date(): void
    {
        $user = $this->createUser('admin');

        $employee1 = $this->createEmployee();
        $employee2 = $this->createEmployee();

        $period = $this->createPeriod();

        $olderReview = $this->createReview(
            $employee1,
            $period,
            $user,
            'approved',
            now()->subMonths(6)->toDateString()
        );

        $newerReview = $this->createReview(
            $employee2,
            $period,
            $user,
            'approved',
            now()->subMonth()->toDateString()
        );

        $result = $this->service->getHistory($user);

        $this->assertCount(2, $result);
        $this->assertEquals(
            $newerReview->id,
            $result->first()->id
        );
        $this->assertEquals(
            $olderReview->id,
            $result->last()->id
        );
    }


    public function test_history_loads_required_relationships(): void
    {
        $user = $this->createUser('admin');

        $employee = $this->createEmployee();

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $user
        );

        $result = $this->service->getHistory($user);

        $history = $result->first();

        $this->assertEquals($review->id, $history->id);

        $this->assertTrue(
            $history->relationLoaded('employee')
        );

        $this->assertTrue(
            $history->relationLoaded('period')
        );

        $this->assertTrue(
            $history->relationLoaded('reviewer')
        );
    }
}
