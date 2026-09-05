<?php

namespace Tests\Unit\Performance;

use App\Models\Department;
use App\Models\PerformanceIndicator;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\Position;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\Performance\PerformanceScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PerformanceScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceScoreService $performanceScoreService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->performanceScoreService = app(
            PerformanceScoreService::class
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

    private function createReview(): PerformanceReview
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

        $period = PerformancePeriod::query()->create([
            'name' => 'Performance Review 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'draft',
            'description' => 'Annual performance review period.',
        ]);

        return PerformanceReview::query()->create([
            'employee_id' => $employee->id,
            'performance_period_id' => $period->id,
            'reviewer_id' => $user->id,
            'review_type' => 'annual',
            'status' => 'draft',
            'overall_score' => null,
            'review_date' => '2026-12-01',
            'comments' => 'Test review.',
        ]);
    }

    private function createIndicator(
        float $weight,
        string $name = 'Performance Indicator'
    ): PerformanceIndicator {
        return PerformanceIndicator::query()->create([
            'name' => $name . ' ' . uniqid(),
            'description' => 'Test performance indicator.',
            'category' => 'Performance',
            'target' => 100,
            'weight' => $weight,
            'measurement_type' => 'score',
            'is_active' => true,
        ]);
    }

    private function createItem(
        PerformanceReview $review,
        PerformanceIndicator $indicator,
        ?float $score
    ): void {
        $review->items()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => $score,
            'comment' => 'Test score.',
        ]);
    }

    public function test_it_can_calculate_overall_score(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator(
            weight: 60,
            name: 'Quality'
        );

        $indicator2 = $this->createIndicator(
            weight: 40,
            name: 'Productivity'
        );

        $this->createItem(
            $review,
            $indicator1,
            80
        );

        $this->createItem(
            $review,
            $indicator2,
            90
        );

        $result = $this->performanceScoreService->calculate(
            $review
        );

        /*
         * (80 × 60 / 100) + (90 × 40 / 100)
         * = 48 + 36
         * = 84
         */
        $this->assertSame(
            84.0,
            $result
        );
    }

    public function test_it_can_calculate_score_with_multiple_indicators(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator(
            weight: 20,
            name: 'Quality'
        );

        $indicator2 = $this->createIndicator(
            weight: 30,
            name: 'Productivity'
        );

        $indicator3 = $this->createIndicator(
            weight: 50,
            name: 'Discipline'
        );

        $this->createItem(
            $review,
            $indicator1,
            70
        );

        $this->createItem(
            $review,
            $indicator2,
            80
        );

        $this->createItem(
            $review,
            $indicator3,
            90
        );

        $result = $this->performanceScoreService->calculate(
            $review
        );

        /*
         * (70 × 20 / 100) = 14
         * (80 × 30 / 100) = 24
         * (90 × 50 / 100) = 45
         *
         * Total = 83
         */
        $this->assertSame(
            83.0,
            $result
        );
    }

    public function test_it_treats_null_score_as_zero(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator(
            weight: 50,
            name: 'Quality'
        );

        $indicator2 = $this->createIndicator(
            weight: 50,
            name: 'Productivity'
        );

        $this->createItem(
            $review,
            $indicator1,
            null
        );

        $this->createItem(
            $review,
            $indicator2,
            80
        );

        $result = $this->performanceScoreService->calculate(
            $review
        );

        /*
         * (0 × 50 / 100) + (80 × 50 / 100)
         * = 0 + 40
         * = 40
         */
        $this->assertSame(
            40.0,
            $result
        );
    }

    public function test_it_throws_exception_when_total_weight_is_not_100_percent(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator(
            weight: 60,
            name: 'Quality'
        );

        $indicator2 = $this->createIndicator(
            weight: 30,
            name: 'Productivity'
        );

        $this->createItem(
            $review,
            $indicator1,
            80
        );

        $this->createItem(
            $review,
            $indicator2,
            90
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Total bobot indikator performance harus 100%.'
        );

        $this->performanceScoreService->calculate(
            $review
        );
    }

    public function test_it_throws_exception_when_review_has_no_items(): void
    {
        $review = $this->createReview();

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance review belum memiliki indikator.'
        );

        $this->performanceScoreService->calculate(
            $review
        );
    }

    public function test_it_rounds_overall_score_to_two_decimal_places(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator(
            weight: 33.33,
            name: 'Quality'
        );

        $indicator2 = $this->createIndicator(
            weight: 66.67,
            name: 'Productivity'
        );

        $this->createItem(
            $review,
            $indicator1,
            80.55
        );

        $this->createItem(
            $review,
            $indicator2,
            90.75
        );

        $result = $this->performanceScoreService->calculate(
            $review
        );

        /*
         * (80.55 × 33.33 / 100)
         * + (90.75 × 66.67 / 100)
         *
         * ≈ 87.349...
         *
         * Rounded = 87.35
         */
        $this->assertSame(
            87.35,
            $result
        );
    }

    public function test_it_can_calculate_and_save_overall_score(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator(
            weight: 60,
            name: 'Quality'
        );

        $indicator2 = $this->createIndicator(
            weight: 40,
            name: 'Productivity'
        );

        $this->createItem(
            $review,
            $indicator1,
            80
        );

        $this->createItem(
            $review,
            $indicator2,
            90
        );

        $result = $this->performanceScoreService->calculateAndSave(
            $review
        );

        $this->assertInstanceOf(
            PerformanceReview::class,
            $result
        );

        $this->assertEquals(
            84.0,
            $result->overall_score
        );

        $this->assertDatabaseHas(
            'performance_reviews',
            [
                'id' => $review->id,
                'overall_score' => 84,
            ]
        );
    }

    public function test_it_cannot_calculate_and_save_when_total_weight_is_invalid(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator(
            weight: 50,
            name: 'Quality'
        );

        $indicator2 = $this->createIndicator(
            weight: 30,
            name: 'Productivity'
        );

        $this->createItem(
            $review,
            $indicator1,
            80
        );

        $this->createItem(
            $review,
            $indicator2,
            90
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Total bobot indikator performance harus 100%.'
        );

        $this->performanceScoreService->calculateAndSave(
            $review
        );
    }
}
