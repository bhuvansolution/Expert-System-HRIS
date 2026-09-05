<?php

namespace Tests\Unit\Leave;

use App\Models\LeaveType;
use App\Services\Leave\LeaveTypeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTypeServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeaveTypeService $leaveTypeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leaveTypeService = app(LeaveTypeService::class);
    }

    public function test_it_can_create_leave_type(): void
    {
        $leaveType = $this->leaveTypeService->create([
            'name' => 'Annual Leave',
            'code' => 'AL',
            'default_days' => 12,
            'description' => 'Annual leave for employees.',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(LeaveType::class, $leaveType);

        $this->assertSame('Annual Leave', $leaveType->name);
        $this->assertSame('AL', $leaveType->code);
        $this->assertSame(12, $leaveType->default_days);
        $this->assertSame(
            'Annual leave for employees.',
            $leaveType->description
        );
        $this->assertSame('active', $leaveType->status);

        $this->assertDatabaseHas('leave_types', [
            'id' => $leaveType->id,
            'name' => 'Annual Leave',
            'code' => 'AL',
            'default_days' => 12,
            'status' => 'active',
        ]);
    }

    public function test_it_can_create_leave_type_without_description(): void
    {
        $leaveType = $this->leaveTypeService->create([
            'name' => 'Sick Leave',
            'code' => 'SL',
            'default_days' => 10,
            'status' => 'active',
        ]);

        $this->assertInstanceOf(LeaveType::class, $leaveType);

        $this->assertNull($leaveType->description);

        $this->assertDatabaseHas('leave_types', [
            'id' => $leaveType->id,
            'code' => 'SL',
            'description' => null,
        ]);
    }

    public function test_it_can_find_leave_type_by_id(): void
    {
        $leaveType = LeaveType::query()->create([
            'name' => 'Annual Leave',
            'code' => 'AL',
            'default_days' => 12,
            'description' => 'Annual leave.',
            'status' => 'active',
        ]);

        $result = $this->leaveTypeService->findById(
            $leaveType->id
        );

        $this->assertInstanceOf(LeaveType::class, $result);
        $this->assertSame($leaveType->id, $result->id);
        $this->assertSame('Annual Leave', $result->name);
    }

    public function test_it_throws_exception_when_leave_type_is_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->leaveTypeService->findById(999999);
    }

    public function test_it_can_search_leave_type_by_name(): void
    {
        LeaveType::query()->create([
            'name' => 'Annual Leave',
            'code' => 'AL',
            'default_days' => 12,
            'description' => 'Annual leave.',
            'status' => 'active',
        ]);

        LeaveType::query()->create([
            'name' => 'Sick Leave',
            'code' => 'SL',
            'default_days' => 10,
            'description' => 'Sick leave.',
            'status' => 'active',
        ]);

        $result = $this->leaveTypeService->paginate(
            perPage: 15,
            search: 'Annual',
        );

        $this->assertSame(1, $result->total());

        $items = $result->items();

        $this->assertSame(
            'AL',
            $items[0]->code
        );
    }

    public function test_it_can_search_leave_type_by_code(): void
    {
        LeaveType::query()->create([
            'name' => 'Annual Leave',
            'code' => 'AL',
            'default_days' => 12,
            'description' => 'Annual leave.',
            'status' => 'active',
        ]);

        LeaveType::query()->create([
            'name' => 'Sick Leave',
            'code' => 'SL',
            'default_days' => 10,
            'description' => 'Sick leave.',
            'status' => 'active',
        ]);

        $result = $this->leaveTypeService->paginate(
            perPage: 15,
            search: 'SL',
        );

        $this->assertSame(1, $result->total());

        $items = $result->items();

        $this->assertSame(
            'Sick Leave',
            $items[0]->name
        );
    }

    public function test_it_can_search_leave_type_by_description(): void
    {
        LeaveType::query()->create([
            'name' => 'Annual Leave',
            'code' => 'AL',
            'default_days' => 12,
            'description' => 'Paid annual vacation.',
            'status' => 'active',
        ]);

        LeaveType::query()->create([
            'name' => 'Sick Leave',
            'code' => 'SL',
            'default_days' => 10,
            'description' => 'Leave because of illness.',
            'status' => 'active',
        ]);

        $result = $this->leaveTypeService->paginate(
            perPage: 15,
            search: 'illness',
        );

        $this->assertSame(1, $result->total());

        $items = $result->items();

        $this->assertSame(
            'SL',
            $items[0]->code
        );
    }

    public function test_it_can_update_leave_type(): void
    {
        $leaveType = LeaveType::query()->create([
            'name' => 'Annual Leave',
            'code' => 'AL',
            'default_days' => 12,
            'description' => 'Old description.',
            'status' => 'active',
        ]);

        $result = $this->leaveTypeService->update(
            $leaveType,
            [
                'name' => 'Annual Leave Updated',
                'default_days' => 15,
                'description' => 'Updated description.',
                'status' => 'inactive',
            ],
        );

        $this->assertSame(
            'Annual Leave Updated',
            $result->name
        );

        $this->assertSame(15, $result->default_days);

        $this->assertSame(
            'Updated description.',
            $result->description
        );

        $this->assertSame(
            'inactive',
            $result->status
        );

        $this->assertDatabaseHas('leave_types', [
            'id' => $leaveType->id,
            'name' => 'Annual Leave Updated',
            'default_days' => 15,
            'description' => 'Updated description.',
            'status' => 'inactive',
        ]);
    }

    public function test_it_can_delete_leave_type(): void
    {
        $leaveType = LeaveType::query()->create([
            'name' => 'Annual Leave',
            'code' => 'AL',
            'default_days' => 12,
            'description' => 'Annual leave.',
            'status' => 'active',
        ]);

        $this->leaveTypeService->delete($leaveType);

        $this->assertDatabaseMissing('leave_types', [
            'id' => $leaveType->id,
        ]);
    }
}
