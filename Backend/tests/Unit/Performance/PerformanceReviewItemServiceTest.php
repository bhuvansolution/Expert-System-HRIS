<?php

namespace Tests\Unit\Performance;

use App\Models\Department;
use App\Models\PerformanceIndicator;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewItem;
use App\Models\Position;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\Performance\PerformanceReviewItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PerformanceReviewItemServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceReviewItemService $performanceReviewItemService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->performanceReviewItemService = app(
            PerformanceReviewItemService::class
        );
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

    private function createEmployee(): int
    {
        $user = User::factory()->create();

        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employeeService = app(EmployeeService::class);

        $employee = $employeeService->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => null,
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
            'history_reason' => 'Initial employment',
            'history_notes' => 'Employee joined the company.',
        ]);

        return $employee->id;
    }

    private function createPerformancePeriod(): PerformancePeriod
    {
        return PerformancePeriod::query()->create([
            'name' => 'Performance Review 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'draft',
            'description' => 'Annual performance review period.',
        ]);
    }

    private function createReview(
        string $status = 'draft'
    ): PerformanceReview {
        $employeeId = $this->createEmployee();

        $user = User::factory()->create();

        $period = $this->createPerformancePeriod();

        return PerformanceReview::query()->create([
            'employee_id' => $employeeId,
            'performance_period_id' => $period->id,
            'reviewer_id' => $user->id,
            'review_type' => 'annual',
            'status' => $status,
            'overall_score' => 80,
            'review_date' => '2026-12-01',
            'comments' => 'Test review.',
        ]);
    }

    private function createIndicator(
        bool $isActive = true
    ): PerformanceIndicator {
        return PerformanceIndicator::query()->create([
            'name' => 'Indicator ' . uniqid(),
            'description' => 'Test performance indicator.',
            'category' => 'Performance',
            'target' => 100,
            'weight' => 20,
            'measurement_type' => 'score',
            'is_active' => $isActive,
        ]);
    }

    public function test_it_can_get_items_by_review(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator();
        $indicator2 = $this->createIndicator();

        $review->items()->create([
            'performance_indicator_id' => $indicator1->id,
            'score' => 80,
            'comment' => 'Good performance.',
        ]);

        $review->items()->create([
            'performance_indicator_id' => $indicator2->id,
            'score' => 90,
            'comment' => 'Very good performance.',
        ]);

        $result = $this->performanceReviewItemService->getByReview(
            $review
        );

        $this->assertCount(2, $result);

        $this->assertTrue(
            $result->every(
                fn(PerformanceReviewItem $item) =>
                $item->relationLoaded('indicator')
            )
        );

        $this->assertTrue(
            $result->contains(
                fn(PerformanceReviewItem $item) =>
                $item->performance_indicator_id === $indicator1->id
            )
        );

        $this->assertTrue(
            $result->contains(
                fn(PerformanceReviewItem $item) =>
                $item->performance_indicator_id === $indicator2->id
            )
        );
    }

    public function test_it_can_get_item_by_id(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator();

        $item = $review->items()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 85,
            'comment' => 'Good performance.',
        ]);

        $result = $this->performanceReviewItemService->getById(
            $item
        );

        $this->assertInstanceOf(
            PerformanceReviewItem::class,
            $result
        );

        $this->assertSame(
            $item->id,
            $result->id
        );

        $this->assertTrue(
            $result->relationLoaded('indicator')
        );

        $this->assertSame(
            $indicator->id,
            $result->indicator->id
        );
    }

    public function test_it_can_create_item(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator();

        $item = $this->performanceReviewItemService->create(
            $review,
            [
                'performance_indicator_id' => $indicator->id,
                'score' => 90,
                'comment' => 'Excellent performance.',
            ]
        );

        $this->assertInstanceOf(
            PerformanceReviewItem::class,
            $item
        );

        $this->assertSame(
            $review->id,
            $item->performance_review_id
        );

        $this->assertSame(
            $indicator->id,
            $item->performance_indicator_id
        );

        $this->assertEquals(
            90,
            $item->score
        );

        $this->assertSame(
            'Excellent performance.',
            $item->comment
        );

        $this->assertTrue(
            $item->relationLoaded('indicator')
        );

        $this->assertDatabaseHas(
            'performance_review_items',
            [
                'id' => $item->id,
                'performance_review_id' => $review->id,
                'performance_indicator_id' => $indicator->id,
                'score' => 90,
                'comment' => 'Excellent performance.',
            ]
        );
    }

    public function test_it_cannot_create_item_for_approved_review(): void
    {
        $review = $this->createReview('approved');

        $indicator = $this->createIndicator();

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance review yang sudah approved tidak dapat diubah.'
        );

        $this->performanceReviewItemService->create(
            $review,
            [
                'performance_indicator_id' => $indicator->id,
                'score' => 90,
            ]
        );
    }

    public function test_it_cannot_create_item_with_inactive_indicator(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator(false);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance indicator yang dipilih tidak aktif.'
        );

        $this->performanceReviewItemService->create(
            $review,
            [
                'performance_indicator_id' => $indicator->id,
                'score' => 90,
            ]
        );
    }

    public function test_it_cannot_create_duplicate_indicator_in_same_review(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator();

        $review->items()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 80,
            'comment' => 'Existing item.',
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance indicator tersebut sudah digunakan dalam review.'
        );

        $this->performanceReviewItemService->create(
            $review,
            [
                'performance_indicator_id' => $indicator->id,
                'score' => 90,
                'comment' => 'Duplicate item.',
            ]
        );
    }

    public function test_it_can_update_item(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator();

        $item = $review->items()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 70,
            'comment' => 'Initial comment.',
        ]);

        $result = $this->performanceReviewItemService->update(
            $item,
            [
                'score' => 90,
                'comment' => 'Updated comment.',
            ]
        );

        $this->assertSame(
            $item->id,
            $result->id
        );

        $this->assertEquals(
            90,
            $result->score
        );

        $this->assertSame(
            'Updated comment.',
            $result->comment
        );

        $this->assertSame(
            $indicator->id,
            $result->performance_indicator_id
        );

        $this->assertTrue(
            $result->relationLoaded('indicator')
        );

        $this->assertDatabaseHas(
            'performance_review_items',
            [
                'id' => $item->id,
                'score' => 90,
                'comment' => 'Updated comment.',
            ]
        );
    }

    public function test_it_cannot_update_item_for_approved_review(): void
    {
        $review = $this->createReview('approved');

        $indicator = $this->createIndicator();

        $item = $review->items()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 80,
            'comment' => 'Initial comment.',
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance review yang sudah approved tidak dapat diubah.'
        );

        $this->performanceReviewItemService->update(
            $item,
            [
                'score' => 95,
            ]
        );
    }

    public function test_it_can_change_indicator_when_updating_item(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator();
        $indicator2 = $this->createIndicator();

        $item = $review->items()->create([
            'performance_indicator_id' => $indicator1->id,
            'score' => 80,
            'comment' => 'Initial comment.',
        ]);

        $result = $this->performanceReviewItemService->update(
            $item,
            [
                'performance_indicator_id' => $indicator2->id,
            ]
        );

        $this->assertSame(
            $indicator2->id,
            $result->performance_indicator_id
        );

        $this->assertTrue(
            $result->relationLoaded('indicator')
        );

        $this->assertSame(
            $indicator2->id,
            $result->indicator->id
        );

        $this->assertDatabaseHas(
            'performance_review_items',
            [
                'id' => $item->id,
                'performance_indicator_id' => $indicator2->id,
            ]
        );
    }

    public function test_it_cannot_change_to_inactive_indicator(): void
    {
        $review = $this->createReview();

        $activeIndicator = $this->createIndicator();
        $inactiveIndicator = $this->createIndicator(false);

        $item = $review->items()->create([
            'performance_indicator_id' => $activeIndicator->id,
            'score' => 80,
            'comment' => 'Initial comment.',
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance indicator yang dipilih tidak aktif.'
        );

        $this->performanceReviewItemService->update(
            $item,
            [
                'performance_indicator_id' => $inactiveIndicator->id,
            ]
        );
    }

    public function test_it_cannot_change_to_duplicate_indicator(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator();
        $indicator2 = $this->createIndicator();

        $item = $review->items()->create([
            'performance_indicator_id' => $indicator1->id,
            'score' => 80,
            'comment' => 'Initial comment.',
        ]);

        $review->items()->create([
            'performance_indicator_id' => $indicator2->id,
            'score' => 90,
            'comment' => 'Existing item.',
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance indicator tersebut sudah digunakan dalam review.'
        );

        $this->performanceReviewItemService->update(
            $item,
            [
                'performance_indicator_id' => $indicator2->id,
            ]
        );
    }

    public function test_it_can_delete_item(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator();

        $item = $review->items()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 80,
            'comment' => 'To be deleted.',
        ]);

        $this->performanceReviewItemService->delete(
            $item
        );

        $this->assertDatabaseMissing(
            'performance_review_items',
            [
                'id' => $item->id,
            ]
        );
    }

    public function test_it_cannot_delete_item_from_approved_review(): void
    {
        $review = $this->createReview('approved');

        $indicator = $this->createIndicator();

        $item = $review->items()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 80,
            'comment' => 'Cannot be deleted.',
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance review yang sudah approved tidak dapat diubah.'
        );

        $this->performanceReviewItemService->delete(
            $item
        );

        $this->assertDatabaseHas(
            'performance_review_items',
            [
                'id' => $item->id,
            ]
        );
    }
}
