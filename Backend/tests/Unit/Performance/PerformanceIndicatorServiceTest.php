<?php

namespace Tests\Unit\Performance;

use App\Models\PerformanceIndicator;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewItem;
use App\Models\User;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Services\Performance\PerformanceIndicatorService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceIndicatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceIndicatorService $performanceIndicatorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->performanceIndicatorService = app(
            PerformanceIndicatorService::class
        );
    }

    private function createIndicator(
        string $name = 'Communication',
        ?string $description = 'Communication performance indicator.',
        ?string $category = 'Behavioral',
        ?float $target = 80,
        float $weight = 20,
        string $measurementType = 'score',
        bool $isActive = true,
    ): PerformanceIndicator {
        return PerformanceIndicator::query()->create([
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'target' => $target,
            'weight' => $weight,
            'measurement_type' => $measurementType,
            'is_active' => $isActive,
        ]);
    }

    public function test_it_can_get_all_performance_indicators(): void
    {
        $this->createIndicator(
            name: 'Communication',
        );

        $this->createIndicator(
            name: 'Leadership',
        );

        $result = $this->performanceIndicatorService->getAll();

        $this->assertCount(
            2,
            $result
        );
    }


    public function test_it_can_get_only_active_indicators(): void
    {
        $this->createIndicator(
            name: 'Communication',
            isActive: true,
        );

        $this->createIndicator(
            name: 'Leadership',
            isActive: false,
        );

        $result = $this->performanceIndicatorService->getActive();

        $this->assertCount(
            1,
            $result
        );

        $this->assertTrue(
            $result->first()->is_active
        );

        $this->assertEquals(
            'Communication',
            $result->first()->name
        );
    }



    public function test_it_can_get_indicator_by_id(): void
    {
        $indicator = $this->createIndicator();

        $result = $this->performanceIndicatorService->getById(
            $indicator->id
        );

        $this->assertInstanceOf(
            PerformanceIndicator::class,
            $result
        );

        $this->assertEquals(
            $indicator->id,
            $result->id
        );

        $this->assertEquals(
            'Communication',
            $result->name
        );

        $this->assertEquals(
            80.00,
            (float) $result->target
        );

        $this->assertEquals(
            20.00,
            (float) $result->weight
        );

        $this->assertEquals(
            0,
            $result->review_items_count
        );
    }

    public function test_it_throws_exception_when_indicator_is_not_found(): void
    {
        $this->expectException(
            ModelNotFoundException::class
        );

        $this->performanceIndicatorService->getById(
            999999
        );
    }

    public function test_it_can_get_indicator_with_review_items_count(): void
    {
        $indicator = $this->createIndicator();

        $department = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $position = Position::query()->create([
            'code' => 'STAFF',
            'name' => 'HR Staff',
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

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

        $period = PerformancePeriod::query()->create([
            'name' => 'Performance Review 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'draft',
            'description' => 'Annual performance review.',
        ]);

        $review = PerformanceReview::query()->create([
            'employee_id' => $employee->id,
            'performance_period_id' => $period->id,
            'reviewer_id' => $user->id,
            'review_type' => 'annual',
            'status' => 'draft',
            'overall_score' => 80,
            'review_date' => '2026-12-01',
            'comments' => 'Test review.',
        ]);

        PerformanceReviewItem::query()->create([
            'performance_review_id' => $review->id,
            'performance_indicator_id' => $indicator->id,
            'score' => 85,
            'comment' => 'Good performance.',
        ]);

        $result = $this->performanceIndicatorService->getById(
            $indicator->id
        );

        $this->assertEquals(
            1,
            $result->review_items_count
        );
    }

    public function test_it_can_create_performance_indicator(): void
    {
        $indicator = $this->performanceIndicatorService->create([
            'name' => 'Leadership',
            'description' => 'Leadership performance indicator.',
            'category' => 'Behavioral',
            'target' => 85,
            'weight' => 25,
            'measurement_type' => 'score',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(
            PerformanceIndicator::class,
            $indicator
        );

        $this->assertEquals(
            'Leadership',
            $indicator->name
        );

        $this->assertEquals(
            'Behavioral',
            $indicator->category
        );

        $this->assertEquals(
            85.00,
            (float) $indicator->target
        );

        $this->assertEquals(
            25.00,
            (float) $indicator->weight
        );

        $this->assertTrue(
            $indicator->is_active
        );

        $this->assertDatabaseHas('performance_indicators', [
            'id' => $indicator->id,
            'name' => 'Leadership',
            'category' => 'Behavioral',
            'is_active' => true,
        ]);
    }

    public function test_it_can_create_indicator_without_optional_fields(): void
    {
        $indicator = $this->performanceIndicatorService->create([
            'name' => 'Attendance',
        ]);

        $this->assertInstanceOf(
            PerformanceIndicator::class,
            $indicator
        );

        $this->assertNull(
            $indicator->description
        );

        $this->assertNull(
            $indicator->category
        );

        $this->assertNull(
            $indicator->target
        );

        $this->assertEquals(
            0.00,
            (float) $indicator->weight
        );

        $this->assertNull(
            $indicator->measurement_type
        );

        $this->assertNull(
            $indicator->is_active
        );
    }

    public function test_it_can_update_performance_indicator(): void
    {
        $indicator = $this->createIndicator();

        $result = $this->performanceIndicatorService->update(
            $indicator,
            [
                'name' => 'Effective Communication',
                'description' => 'Updated indicator.',
                'category' => 'Behavioral',
                'target' => 90,
                'weight' => 30,
                'measurement_type' => 'percentage',
                'is_active' => false,
            ]
        );

        $this->assertEquals(
            'Effective Communication',
            $result->name
        );

        $this->assertEquals(
            'Updated indicator.',
            $result->description
        );

        $this->assertEquals(
            90.00,
            (float) $result->target
        );

        $this->assertEquals(
            30.00,
            (float) $result->weight
        );

        $this->assertEquals(
            'percentage',
            $result->measurement_type
        );

        $this->assertFalse(
            $result->is_active
        );

        $this->assertDatabaseHas('performance_indicators', [
            'id' => $indicator->id,
            'name' => 'Effective Communication',
            'is_active' => false,
        ]);
    }

    public function test_it_can_delete_performance_indicator(): void
    {
        $indicator = $this->createIndicator();

        $this->performanceIndicatorService->delete(
            $indicator
        );

        $this->assertDatabaseMissing('performance_indicators', [
            'id' => $indicator->id,
        ]);
    }
}
