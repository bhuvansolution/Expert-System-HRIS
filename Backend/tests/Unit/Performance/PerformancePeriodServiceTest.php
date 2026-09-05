<?php

namespace Tests\Unit\Performance;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\Position;
use App\Models\User;
use App\Services\Performance\PerformancePeriodService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformancePeriodServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformancePeriodService $performancePeriodService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->performancePeriodService = app(
            PerformancePeriodService::class
        );
    }

    private function createPerformancePeriod(
        string $name = 'Performance Review 2026',
        string $startDate = '2026-01-01',
        string $endDate = '2026-12-31',
        string $status = 'draft',
        ?string $description = 'Annual performance review period.',
    ): PerformancePeriod {
        return PerformancePeriod::query()->create([
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
            'description' => $description,
        ]);
    }

    public function test_it_can_get_all_performance_periods(): void
    {
        $this->createPerformancePeriod(
            name: 'Performance Review 2025',
            startDate: '2025-01-01',
            endDate: '2025-12-31',
        );

        $this->createPerformancePeriod(
            name: 'Performance Review 2026',
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $result = $this->performancePeriodService->getAll();

        $this->assertCount(
            2,
            $result
        );

        $this->assertEquals(
            'Performance Review 2026',
            $result->first()->name
        );
    }

    public function test_it_orders_performance_periods_by_latest_start_date(): void
    {
        $this->createPerformancePeriod(
            name: 'Performance Review 2025',
            startDate: '2025-01-01',
            endDate: '2025-12-31',
        );

        $this->createPerformancePeriod(
            name: 'Performance Review 2026',
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $this->createPerformancePeriod(
            name: 'Performance Review 2027',
            startDate: '2027-01-01',
            endDate: '2027-12-31',
        );

        $result = $this->performancePeriodService->getAll();

        $this->assertEquals(
            'Performance Review 2027',
            $result->first()->name
        );

        $this->assertEquals(
            'Performance Review 2025',
            $result->last()->name
        );
    }

    public function test_it_can_get_performance_period_by_id(): void
    {
        $period = $this->createPerformancePeriod();

        $result = $this->performancePeriodService->getById(
            $period->id
        );

        $this->assertInstanceOf(
            PerformancePeriod::class,
            $result
        );

        $this->assertEquals(
            $period->id,
            $result->id
        );

        $this->assertEquals(
            'Performance Review 2026',
            $result->name
        );

        $this->assertEquals(
            0,
            $result->reviews_count
        );
    }

    public function test_it_throws_exception_when_performance_period_is_not_found(): void
    {
        $this->expectException(
            ModelNotFoundException::class
        );

        $this->performancePeriodService->getById(999999);
    }

    private function createDepartmentForReview(): Department
    {
        return Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPositionForReview(): Position
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

    public function test_it_can_get_performance_period_with_review_count(): void
    {
        $department = $this->createDepartmentForReview();
        $position = $this->createPositionForReview();

        $employee = Employee::query()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-001',
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

        $user = User::factory()->create();

        $period = $this->createPerformancePeriod();

        PerformanceReview::query()->create([
            'employee_id' => $employee->id,
            'performance_period_id' => $period->id,
            'reviewer_id' => $user->id,
            'review_type' => 'annual',
            'status' => 'draft',
            'overall_score' => 80,
            'review_date' => '2026-12-01',
            'comments' => 'Test review.',
        ]);

        $result = $this->performancePeriodService->getById(
            $period->id
        );

        $this->assertEquals(
            1,
            $result->reviews_count
        );
    }

    public function test_it_can_create_performance_period(): void
    {
        $period = $this->performancePeriodService->create([
            'name' => 'Performance Review 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'draft',
            'description' => 'Annual performance review period.',
        ]);

        $this->assertInstanceOf(
            PerformancePeriod::class,
            $period
        );

        $this->assertEquals(
            'Performance Review 2026',
            $period->name
        );

        $this->assertEquals(
            '2026-01-01',
            $period->start_date->toDateString()
        );

        $this->assertEquals(
            '2026-12-31',
            $period->end_date->toDateString()
        );

        $this->assertEquals(
            'draft',
            $period->status
        );

        $this->assertDatabaseHas('performance_periods', [
            'id' => $period->id,
            'name' => 'Performance Review 2026',
            'status' => 'draft',
        ]);
    }

    public function test_it_can_create_performance_period_without_description(): void
    {
        $period = $this->performancePeriodService->create([
            'name' => 'Performance Review 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'draft',
        ]);

        $this->assertNull(
            $period->description
        );

        $this->assertDatabaseHas('performance_periods', [
            'id' => $period->id,
            'name' => 'Performance Review 2027',
            'description' => null,
        ]);
    }

    public function test_it_can_update_performance_period(): void
    {
        $period = $this->createPerformancePeriod();

        $result = $this->performancePeriodService->update(
            $period,
            [
                'name' => 'Performance Review 2026 Updated',
                'start_date' => '2026-02-01',
                'end_date' => '2026-11-30',
                'status' => 'active',
                'description' => 'Updated description.',
            ]
        );

        $this->assertEquals(
            'Performance Review 2026 Updated',
            $result->name
        );

        $this->assertEquals(
            '2026-02-01',
            $result->start_date->toDateString()
        );

        $this->assertEquals(
            '2026-11-30',
            $result->end_date->toDateString()
        );

        $this->assertEquals(
            'active',
            $result->status
        );

        $this->assertEquals(
            'Updated description.',
            $result->description
        );

        $this->assertDatabaseHas('performance_periods', [
            'id' => $period->id,
            'name' => 'Performance Review 2026 Updated',
            'status' => 'active',
        ]);
    }

    public function test_it_can_delete_performance_period(): void
    {
        $period = $this->createPerformancePeriod();

        $this->performancePeriodService->delete(
            $period
        );

        $this->assertDatabaseMissing('performance_periods', [
            'id' => $period->id,
        ]);
    }
}
