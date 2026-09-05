<?php

namespace Tests\Unit\Competency;

use App\Models\Competency;
use App\Models\CompetencyLevel;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeCompetency;
use App\Models\Position;
use App\Services\Competency\EmployeeCompetencyService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCompetencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeCompetencyService $employeeCompetencyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employeeCompetencyService = app(
            EmployeeCompetencyService::class
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

    private function createCompetency(
        string $code = 'COM-001',
        string $name = 'Communication',
        string $category = 'Behavioral',
    ): Competency {
        return Competency::query()->create([
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'description' => $name . ' competency.',
            'status' => 'active',
        ]);
    }

    private function createCompetencyLevel(
        int $level = 1,
        string $name = 'Beginner',
    ): CompetencyLevel {
        return CompetencyLevel::query()->create([
            'level' => $level,
            'name' => $name,
            'description' => $name . ' competency level.',
        ]);
    }

    private function createEmployeeCompetency(
        Employee $employee,
        Competency $competency,
        CompetencyLevel $competencyLevel,
        ?string $notes = null,
    ): EmployeeCompetency {
        return EmployeeCompetency::query()->create([
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'competency_level_id' => $competencyLevel->id,
            'notes' => $notes,
        ]);
    }

    public function test_it_can_create_employee_competency(): void
    {
        $employee = $this->createEmployee();
        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $result = $this->employeeCompetencyService->create([
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'competency_level_id' => $level->id,
            'notes' => 'Initial competency assessment.',
        ]);

        $this->assertInstanceOf(
            EmployeeCompetency::class,
            $result
        );

        $this->assertEquals(
            $employee->id,
            $result->employee_id
        );

        $this->assertEquals(
            $competency->id,
            $result->competency_id
        );

        $this->assertEquals(
            $level->id,
            $result->competency_level_id
        );

        $this->assertDatabaseHas('employee_competencies', [
            'id' => $result->id,
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'competency_level_id' => $level->id,
        ]);
    }

    public function test_it_can_find_employee_competency_by_id(): void
    {
        $employee = $this->createEmployee();
        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $employeeCompetency = $this->createEmployeeCompetency(
            employee: $employee,
            competency: $competency,
            competencyLevel: $level,
        );

        $result = $this->employeeCompetencyService->findById(
            $employeeCompetency->id
        );

        $this->assertInstanceOf(
            EmployeeCompetency::class,
            $result
        );

        $this->assertEquals(
            $employeeCompetency->id,
            $result->id
        );

        $this->assertEquals(
            $employee->id,
            $result->employee_id
        );
    }

    public function test_it_throws_exception_when_employee_competency_is_not_found(): void
    {
        $this->expectException(
            ModelNotFoundException::class
        );

        $this->employeeCompetencyService->findById(999999);
    }

    public function test_it_can_update_employee_competency(): void
    {
        $employee = $this->createEmployee();
        $competency = $this->createCompetency();

        $levelOne = $this->createCompetencyLevel(
            level: 1,
            name: 'Beginner',
        );

        $levelTwo = $this->createCompetencyLevel(
            level: 2,
            name: 'Intermediate',
        );

        $employeeCompetency = $this->createEmployeeCompetency(
            employee: $employee,
            competency: $competency,
            competencyLevel: $levelOne,
            notes: 'Initial assessment.',
        );

        $result = $this->employeeCompetencyService->update(
            $employeeCompetency,
            [
                'competency_level_id' => $levelTwo->id,
                'notes' => 'Updated assessment.',
            ]
        );

        $this->assertEquals(
            $levelTwo->id,
            $result->competency_level_id
        );

        $this->assertEquals(
            'Updated assessment.',
            $result->notes
        );

        $this->assertDatabaseHas('employee_competencies', [
            'id' => $employeeCompetency->id,
            'competency_level_id' => $levelTwo->id,
            'notes' => 'Updated assessment.',
        ]);
    }

    public function test_it_can_delete_employee_competency(): void
    {
        $employee = $this->createEmployee();
        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $employeeCompetency = $this->createEmployeeCompetency(
            employee: $employee,
            competency: $competency,
            competencyLevel: $level,
        );

        $this->employeeCompetencyService->delete(
            $employeeCompetency
        );

        $this->assertDatabaseMissing('employee_competencies', [
            'id' => $employeeCompetency->id,
        ]);
    }

    public function test_it_can_paginate_employee_competencies(): void
    {
        $employee = $this->createEmployee();

        $competencyOne = $this->createCompetency(
            code: 'COM-001',
            name: 'Communication',
        );

        $competencyTwo = $this->createCompetency(
            code: 'LEAD-001',
            name: 'Leadership',
        );

        $level = $this->createCompetencyLevel();

        $this->createEmployeeCompetency(
            employee: $employee,
            competency: $competencyOne,
            competencyLevel: $level,
        );

        $this->createEmployeeCompetency(
            employee: $employee,
            competency: $competencyTwo,
            competencyLevel: $level,
        );

        $result = $this->employeeCompetencyService->paginate();

        $this->assertCount(
            2,
            $result->items()
        );

        $this->assertEquals(
            2,
            $result->total()
        );
    }

    public function test_it_can_search_employee_competency_by_employee(): void
    {
        $employee = $this->createEmployee();

        $otherEmployee = $this->createEmployee(
            employeeNumber: 'EMP-002',
            department: Department::query()->first(),
            position: Position::query()->first(),
        );

        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $this->createEmployeeCompetency(
            employee: $employee,
            competency: $competency,
            competencyLevel: $level,
        );

        $this->createEmployeeCompetency(
            employee: $otherEmployee,
            competency: $competency,
            competencyLevel: $level,
        );

        $result = $this->employeeCompetencyService->paginate(
            search: 'EMP-001'
        );

        $this->assertCount(
            1,
            $result->items()
        );

        $this->assertEquals(
            $employee->id,
            $result->items()[0]->employee_id
        );
    }

    public function test_it_can_search_employee_competency_by_competency(): void
    {
        $employee = $this->createEmployee();

        $communication = $this->createCompetency(
            code: 'COM-001',
            name: 'Communication',
        );

        $leadership = $this->createCompetency(
            code: 'LEAD-001',
            name: 'Leadership',
        );

        $level = $this->createCompetencyLevel();

        $this->createEmployeeCompetency(
            employee: $employee,
            competency: $communication,
            competencyLevel: $level,
        );

        $this->createEmployeeCompetency(
            employee: $employee,
            competency: $leadership,
            competencyLevel: $level,
        );

        $result = $this->employeeCompetencyService->paginate(
            search: 'Communication'
        );

        $this->assertCount(
            1,
            $result->items()
        );

        $this->assertEquals(
            $communication->id,
            $result->items()[0]->competency_id
        );
    }

    public function test_it_can_search_employee_competency_by_level(): void
    {
        $employee = $this->createEmployee();

        $competencyOne = $this->createCompetency(
            code: 'COM-001',
            name: 'Communication',
        );

        $competencyTwo = $this->createCompetency(
            code: 'LEAD-001',
            name: 'Leadership',
        );

        $beginner = $this->createCompetencyLevel(
            level: 1,
            name: 'Beginner',
        );

        $intermediate = $this->createCompetencyLevel(
            level: 2,
            name: 'Intermediate',
        );

        $this->createEmployeeCompetency(
            employee: $employee,
            competency: $competencyOne,
            competencyLevel: $beginner,
        );

        $this->createEmployeeCompetency(
            employee: $employee,
            competency: $competencyTwo,
            competencyLevel: $intermediate,
        );

        $result = $this->employeeCompetencyService->paginate(
            search: 'Intermediate'
        );

        $this->assertCount(
            1,
            $result->items()
        );

        $this->assertEquals(
            $intermediate->id,
            $result->items()[0]->competency_level_id
        );
    }
}
