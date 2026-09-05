<?php

namespace Tests\Unit\Performance;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformanceIndicator;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewItem;
use App\Models\Position;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\Performance\PerformanceReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class PerformanceReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PerformanceReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();

        $this->service = app(PerformanceReviewService::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

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

    private function createUser(string $role): User
    {
        $user = User::factory()->create();

        $user->assignRole($role);

        return $user;
    }

    private function createEmployee(
        User $user,
        ?Employee $manager = null
    ): Employee {
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

        $data = [
            'employee_number' => 'EMP-' . uniqid(),
            'user_id' => $user->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
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

        if ($manager) {
            $data['manager_id'] = $manager->id;
        }

        return $employeeService->create($data);
    }

    private function createPeriod(): PerformancePeriod
    {
        return PerformancePeriod::create([
            'name' => 'Performance Period 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'description' => 'Performance period 2026',
        ]);
    }

    private function createReview(
        Employee $employee,
        PerformancePeriod $period,
        User $reviewer,
        array $overrides = []
    ): PerformanceReview {
        return PerformanceReview::create(array_merge([
            'employee_id' => $employee->id,
            'performance_period_id' => $period->id,
            'reviewer_id' => $reviewer->id,
            'review_type' => 'annual',
            'status' => 'draft',
            'overall_score' => null,
            'review_date' => null,
            'comments' => null,
        ], $overrides));
    }

    private function createIndicator(
        string $code,
        float $weight = 100
    ): PerformanceIndicator {
        return PerformanceIndicator::create([
            'code' => $code,
            'name' => 'Indicator ' . $code,
            'description' => 'Test performance indicator',
            'weight' => $weight,
            'measurement_type' => 'score',
            'is_active' => true,
        ]);
    }

    private function createReviewWithItem(
        PerformanceReview $review,
        float $weight = 100,
        float $score = 80
    ): PerformanceReviewItem {
        $indicator = $this->createIndicator(
            'IND-' . uniqid(),
            $weight
        );

        return PerformanceReviewItem::create([
            'performance_review_id' => $review->id,
            'performance_indicator_id' => $indicator->id,
            'score' => $score,
            'comment' => 'Test comment',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | getAll()
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_get_all_performance_reviews(): void
    {
        $admin = $this->createUser('super-admin');

        $employeeUser = User::factory()->create();
        $employee = $this->createEmployee($employeeUser);

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $admin
        );

        $this->createReview(
            $employee,
            $period,
            $admin,
            [
                'review_type' => 'self',
            ]
        );

        $result = $this->service->getAll($admin);

        $this->assertCount(2, $result);
    }

    public function test_admin_can_get_all_performance_reviews(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();
        $employee = $this->createEmployee($employeeUser);

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $admin
        );

        $result = $this->service->getAll($admin);

        $this->assertCount(1, $result);
    }

    public function test_hr_admin_can_get_all_performance_reviews(): void
    {
        $hrAdmin = $this->createUser('hr-admin');

        $employeeUser = User::factory()->create();
        $employee = $this->createEmployee($employeeUser);

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $hrAdmin
        );

        $result = $this->service->getAll($hrAdmin);

        $this->assertCount(1, $result);
    }

    public function test_manager_can_only_get_direct_report_reviews(): void
    {
        $managerUser = $this->createUser('manager');

        $managerEmployee = $this->createEmployee($managerUser);

        $employeeUser = User::factory()->create();

        $directReport = $this->createEmployee(
            $employeeUser,
            $managerEmployee
        );

        $otherEmployeeUser = User::factory()->create();

        $otherEmployee = $this->createEmployee(
            $otherEmployeeUser
        );

        $period = $this->createPeriod();

        $this->createReview(
            $directReport,
            $period,
            $managerUser
        );

        $this->createReview(
            $otherEmployee,
            $period,
            $managerUser
        );

        $result = $this->service->getAll($managerUser);

        $this->assertCount(1, $result);
        $this->assertEquals(
            $directReport->id,
            $result->first()->employee_id
        );
    }

    public function test_employee_can_only_get_own_reviews(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee($employeeUser);

        $otherUser = User::factory()->create();

        $otherEmployee = $this->createEmployee($otherUser);

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $employeeUser,
            [
                'review_type' => 'self',
            ]
        );

        $this->createReview(
            $otherEmployee,
            $period,
            $employeeUser,
            [
                'review_type' => 'self',
            ]
        );

        $result = $this->service->getAll($employeeUser);

        $this->assertCount(1, $result);
        $this->assertEquals(
            $employee->id,
            $result->first()->employee_id
        );
    }

    public function test_user_without_valid_role_gets_empty_collection(): void
    {
        $user = User::factory()->create();

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee($employeeUser);

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $user
        );

        $result = $this->service->getAll($user);

        $this->assertCount(0, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | getById()
    |--------------------------------------------------------------------------
    */

    public function test_it_can_find_performance_review_by_id(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();
        $employee = $this->createEmployee($employeeUser);

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $indicator = $this->createIndicator(
            'IND-GET-' . uniqid(),
            100
        );

        PerformanceReviewItem::create([
            'performance_review_id' => $review->id,
            'performance_indicator_id' => $indicator->id,
            'score' => 90,
            'comment' => 'Good performance',
        ]);

        $result = $this->service->getById($review->id);

        $this->assertEquals($review->id, $result->id);
        $this->assertTrue($result->relationLoaded('employee'));
        $this->assertTrue($result->relationLoaded('period'));
        $this->assertTrue($result->relationLoaded('reviewer'));
        $this->assertTrue($result->relationLoaded('items'));

        $this->assertCount(1, $result->items);
    }

    public function test_it_throws_exception_when_performance_review_is_not_found(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->getById(999999);
    }

    /*
    |--------------------------------------------------------------------------
    | create()
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_performance_review(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $result = $this->service->create(
            $admin,
            [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'review_type' => 'annual',
                'comments' => 'Initial review',
            ]
        );

        $this->assertDatabaseHas(
            'performance_reviews',
            [
                'id' => $result->id,
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'reviewer_id' => $admin->id,
                'status' => 'draft',
            ]
        );

        $this->assertEquals(
            $admin->id,
            $result->reviewer_id
        );

        $this->assertEquals(
            'draft',
            $result->status
        );
    }

    public function test_hr_admin_can_create_performance_review(): void
    {
        $hrAdmin = $this->createUser('hr-admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $result = $this->service->create(
            $hrAdmin,
            [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'review_type' => 'annual',
            ]
        );

        $this->assertEquals(
            $hrAdmin->id,
            $result->reviewer_id
        );

        $this->assertEquals(
            'draft',
            $result->status
        );
    }

    public function test_employee_can_create_only_self_review_for_himself(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $result = $this->service->create(
            $employeeUser,
            [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'review_type' => 'self',
            ]
        );

        $this->assertEquals(
            $employee->id,
            $result->employee_id
        );

        $this->assertEquals(
            $employeeUser->id,
            $result->reviewer_id
        );

        $this->assertEquals(
            'self',
            $result->review_type
        );

        $this->assertEquals(
            'draft',
            $result->status
        );
    }

    public function test_employee_cannot_create_review_for_other_employee(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee(
            $employeeUser
        );

        $otherUser = User::factory()->create();

        $otherEmployee = $this->createEmployee(
            $otherUser
        );

        $period = $this->createPeriod();

        $this->expectException(InvalidArgumentException::class);

        $this->service->create(
            $employeeUser,
            [
                'employee_id' => $otherEmployee->id,
                'performance_period_id' => $period->id,
                'review_type' => 'self',
            ]
        );
    }

    public function test_employee_cannot_create_non_self_review(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $this->expectException(InvalidArgumentException::class);

        $this->service->create(
            $employeeUser,
            [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'review_type' => 'manager',
            ]
        );
    }

    public function test_manager_can_create_review_for_direct_report(): void
    {
        $managerUser = $this->createUser('manager');

        $managerEmployee = $this->createEmployee(
            $managerUser
        );

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser,
            $managerEmployee
        );

        $period = $this->createPeriod();

        $result = $this->service->create(
            $managerUser,
            [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'review_type' => 'manager',
            ]
        );

        $this->assertEquals(
            $employee->id,
            $result->employee_id
        );

        $this->assertEquals(
            $managerUser->id,
            $result->reviewer_id
        );

        $this->assertEquals(
            'manager',
            $result->review_type
        );

        $this->assertEquals(
            'draft',
            $result->status
        );
    }

    public function test_manager_cannot_create_review_for_non_direct_report(): void
    {
        $managerUser = $this->createUser('manager');

        $this->createEmployee(
            $managerUser
        );

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $this->expectException(InvalidArgumentException::class);

        $this->service->create(
            $managerUser,
            [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'review_type' => 'manager',
            ]
        );
    }

    public function test_manager_cannot_create_non_manager_review(): void
    {
        $managerUser = $this->createUser('manager');

        $managerEmployee = $this->createEmployee(
            $managerUser
        );

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser,
            $managerEmployee
        );

        $period = $this->createPeriod();

        $this->expectException(InvalidArgumentException::class);

        $this->service->create(
            $managerUser,
            [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'review_type' => 'self',
            ]
        );
    }

    public function test_user_without_valid_role_cannot_create_performance_review(): void
    {
        $user = User::factory()->create();

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $this->expectException(InvalidArgumentException::class);

        $this->service->create(
            $user,
            [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'review_type' => 'annual',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | update()
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_performance_review(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $result = $this->service->update(
            $admin,
            $review,
            [
                'comments' => 'Updated comment',
                'review_type' => 'annual',
            ]
        );

        $this->assertEquals(
            'Updated comment',
            $result->comments
        );

        $this->assertDatabaseHas(
            'performance_reviews',
            [
                'id' => $review->id,
                'comments' => 'Updated comment',
            ]
        );
    }

    public function test_employee_can_update_own_review(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $employeeUser,
            [
                'review_type' => 'self',
            ]
        );

        $result = $this->service->update(
            $employeeUser,
            $review,
            [
                'comments' => 'Employee updated comment',
            ]
        );

        $this->assertEquals(
            'Employee updated comment',
            $result->comments
        );
    }

    public function test_employee_cannot_update_other_employee_review(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee(
            $employeeUser
        );

        $otherUser = User::factory()->create();

        $otherEmployee = $this->createEmployee(
            $otherUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $otherEmployee,
            $period,
            $employeeUser,
            [
                'review_type' => 'self',
            ]
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->update(
            $employeeUser,
            $review,
            [
                'comments' => 'Unauthorized update',
            ]
        );
    }

    public function test_manager_can_update_direct_report_review(): void
    {
        $managerUser = $this->createUser('manager');

        $managerEmployee = $this->createEmployee(
            $managerUser
        );

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser,
            $managerEmployee
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $managerUser,
            [
                'review_type' => 'manager',
            ]
        );

        $result = $this->service->update(
            $managerUser,
            $review,
            [
                'comments' => 'Manager update',
            ]
        );

        $this->assertEquals(
            'Manager update',
            $result->comments
        );
    }

    public function test_manager_cannot_update_non_direct_report_review(): void
    {
        $managerUser = $this->createUser('manager');

        $this->createEmployee(
            $managerUser
        );

        $otherUser = User::factory()->create();

        $otherEmployee = $this->createEmployee(
            $otherUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $otherEmployee,
            $period,
            $managerUser
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->update(
            $managerUser,
            $review,
            [
                'comments' => 'Unauthorized update',
            ]
        );
    }

    public function test_approved_review_cannot_be_updated(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin,
            [
                'status' => 'approved',
            ]
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->update(
            $admin,
            $review,
            [
                'comments' => 'Cannot update',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | delete()
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_performance_review(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $this->service->delete(
            $admin,
            $review
        );

        $this->assertDatabaseMissing(
            'performance_reviews',
            [
                'id' => $review->id,
            ]
        );
    }

    public function test_employee_can_delete_own_review(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $employeeUser,
            [
                'review_type' => 'self',
            ]
        );

        $this->service->delete(
            $employeeUser,
            $review
        );

        $this->assertDatabaseMissing(
            'performance_reviews',
            [
                'id' => $review->id,
            ]
        );
    }

    public function test_employee_cannot_delete_other_employee_review(): void
    {
        $employeeUser = $this->createUser('employee');

        $this->createEmployee(
            $employeeUser
        );

        $otherUser = User::factory()->create();

        $otherEmployee = $this->createEmployee(
            $otherUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $otherEmployee,
            $period,
            $employeeUser
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->delete(
            $employeeUser,
            $review
        );
    }

    public function test_approved_review_cannot_be_deleted(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin,
            [
                'status' => 'approved',
            ]
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->delete(
            $admin,
            $review
        );
    }

    /*
    |--------------------------------------------------------------------------
    | calculateScore()
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_calculate_review_score(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $this->createReviewWithItem(
            $review,
            100,
            85
        );

        $result = $this->service->calculateScore(
            $admin,
            $review
        );

        $this->assertEquals(
            85,
            (float) $result->overall_score
        );

        $this->assertDatabaseHas(
            'performance_reviews',
            [
                'id' => $review->id,
                'overall_score' => 85,
            ]
        );
    }

    public function test_employee_can_calculate_own_review_score(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $employeeUser,
            [
                'review_type' => 'self',
            ]
        );

        $this->createReviewWithItem(
            $review,
            100,
            90
        );

        $result = $this->service->calculateScore(
            $employeeUser,
            $review
        );

        $this->assertEquals(
            90,
            (float) $result->overall_score
        );
    }

    public function test_user_cannot_calculate_other_employee_review_score(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee(
            $employeeUser
        );

        $otherUser = User::factory()->create();

        $otherEmployee = $this->createEmployee(
            $otherUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $otherEmployee,
            $period,
            $employeeUser
        );

        $this->createReviewWithItem(
            $review,
            100,
            80
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->calculateScore(
            $employeeUser,
            $review
        );
    }

    public function test_approved_review_cannot_be_recalculated(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin,
            [
                'status' => 'approved',
            ]
        );

        $this->createReviewWithItem(
            $review,
            100,
            90
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->calculateScore(
            $admin,
            $review
        );
    }

    /*
    |--------------------------------------------------------------------------
    | submit()
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_submit_draft_review(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $this->createReviewWithItem(
            $review,
            100,
            85
        );

        $result = $this->service->submit(
            $admin,
            $review
        );

        $this->assertEquals(
            'submitted',
            $result->status
        );

        $this->assertEquals(
            85,
            (float) $result->overall_score
        );

        $this->assertNotNull(
            $result->review_date
        );
    }

    public function test_employee_can_submit_own_review(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $employeeUser,
            [
                'review_type' => 'self',
            ]
        );

        $this->createReviewWithItem(
            $review,
            100,
            80
        );

        $result = $this->service->submit(
            $employeeUser,
            $review
        );

        $this->assertEquals(
            'submitted',
            $result->status
        );

        $this->assertEquals(
            80,
            (float) $result->overall_score
        );
    }

    public function test_cannot_submit_non_draft_review(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin,
            [
                'status' => 'submitted',
            ]
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->submit(
            $admin,
            $review
        );
    }

    public function test_cannot_submit_review_without_items(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->submit(
            $admin,
            $review
        );
    }

    public function test_cannot_submit_review_when_item_has_no_score(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $indicator = $this->createIndicator(
            'IND-NOSCORE-' . uniqid(),
            100
        );

        PerformanceReviewItem::create([
            'performance_review_id' => $review->id,
            'performance_indicator_id' => $indicator->id,
            'score' => null,
            'comment' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->submit(
            $admin,
            $review
        );
    }

    public function test_submit_uses_existing_review_date(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin,
            [
                'review_date' => '2026-08-01',
            ]
        );

        $this->createReviewWithItem(
            $review,
            100,
            95
        );

        $result = $this->service->submit(
            $admin,
            $review
        );

        $this->assertEquals(
            '2026-08-01',
            $result->review_date->format('Y-m-d')
        );
    }

    public function test_cannot_submit_review_with_invalid_total_weight(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $indicator = $this->createIndicator(
            'IND-WEIGHT-' . uniqid(),
            50
        );

        PerformanceReviewItem::create([
            'performance_review_id' => $review->id,
            'performance_indicator_id' => $indicator->id,
            'score' => 80,
            'comment' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->submit(
            $admin,
            $review
        );
    }

    /*
    |--------------------------------------------------------------------------
    | approve()
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_approve_submitted_review(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin,
            [
                'status' => 'submitted',
                'overall_score' => 85,
            ]
        );

        $result = $this->service->approve(
            $admin,
            $review
        );

        $this->assertEquals(
            'approved',
            $result->status
        );

        $this->assertDatabaseHas(
            'performance_reviews',
            [
                'id' => $review->id,
                'status' => 'approved',
            ]
        );
    }

    public function test_employee_can_approve_own_submitted_review(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $employeeUser,
            [
                'status' => 'submitted',
                'overall_score' => 90,
            ]
        );

        $result = $this->service->approve(
            $employeeUser,
            $review
        );

        $this->assertEquals(
            'approved',
            $result->status
        );
    }

    public function test_cannot_approve_draft_review(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->approve(
            $admin,
            $review
        );
    }

    public function test_employee_cannot_approve_other_employee_review(): void
    {
        $employeeUser = $this->createUser('employee');

        $this->createEmployee(
            $employeeUser
        );

        $otherUser = User::factory()->create();

        $otherEmployee = $this->createEmployee(
            $otherUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $otherEmployee,
            $period,
            $employeeUser,
            [
                'status' => 'submitted',
            ]
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->approve(
            $employeeUser,
            $review
        );
    }

    /*
    |--------------------------------------------------------------------------
    | reject()
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_reject_submitted_review(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin,
            [
                'status' => 'submitted',
                'overall_score' => 70,
            ]
        );

        $result = $this->service->reject(
            $admin,
            $review
        );

        $this->assertEquals(
            'rejected',
            $result->status
        );

        $this->assertDatabaseHas(
            'performance_reviews',
            [
                'id' => $review->id,
                'status' => 'rejected',
            ]
        );
    }

    public function test_employee_can_reject_own_submitted_review(): void
    {
        $employeeUser = $this->createUser('employee');

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $employeeUser,
            [
                'status' => 'submitted',
                'overall_score' => 80,
            ]
        );

        $result = $this->service->reject(
            $employeeUser,
            $review
        );

        $this->assertEquals(
            'rejected',
            $result->status
        );
    }

    public function test_cannot_reject_draft_review(): void
    {
        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->reject(
            $admin,
            $review
        );
    }

    public function test_employee_cannot_reject_other_employee_review(): void
    {
        $employeeUser = $this->createUser('employee');

        $this->createEmployee(
            $employeeUser
        );

        $otherUser = User::factory()->create();

        $otherEmployee = $this->createEmployee(
            $otherUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $otherEmployee,
            $period,
            $employeeUser,
            [
                'status' => 'submitted',
            ]
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->reject(
            $employeeUser,
            $review
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_user_without_role_cannot_update_review(): void
    {
        $user = User::factory()->create();

        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->update(
            $user,
            $review,
            [
                'comments' => 'Unauthorized',
            ]
        );
    }

    public function test_user_without_role_cannot_delete_review(): void
    {
        $user = User::factory()->create();

        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->delete(
            $user,
            $review
        );
    }

    public function test_user_without_role_cannot_calculate_score(): void
    {
        $user = User::factory()->create();

        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin
        );

        $this->createReviewWithItem(
            $review,
            100,
            80
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->calculateScore(
            $user,
            $review
        );
    }

    public function test_user_without_role_cannot_approve_review(): void
    {
        $user = User::factory()->create();

        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin,
            [
                'status' => 'submitted',
            ]
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->approve(
            $user,
            $review
        );
    }

    public function test_user_without_role_cannot_reject_review(): void
    {
        $user = User::factory()->create();

        $admin = $this->createUser('admin');

        $employeeUser = User::factory()->create();

        $employee = $this->createEmployee(
            $employeeUser
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $admin,
            [
                'status' => 'submitted',
            ]
        );

        $this->expectException(InvalidArgumentException::class);

        $this->service->reject(
            $user,
            $review
        );
    }
}
