<?php

namespace Tests\Unit\Competency;

use App\Models\CompetencyLevel;
use App\Services\Competency\CompetencyLevelService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetencyLevelServiceTest extends TestCase
{
    use RefreshDatabase;

    private CompetencyLevelService $competencyLevelService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->competencyLevelService = app(
            CompetencyLevelService::class
        );
    }

    private function createCompetencyLevel(
        int $level = 1,
        string $name = 'Beginner',
        ?string $description = 'Basic competency level.',
    ): CompetencyLevel {
        return CompetencyLevel::query()->create([
            'level' => $level,
            'name' => $name,
            'description' => $description,
        ]);
    }

    public function test_it_can_create_competency_level(): void
    {
        $competencyLevel = $this->competencyLevelService->create([
            'level' => 1,
            'name' => 'Beginner',
            'description' => 'Basic competency level.',
        ]);

        $this->assertInstanceOf(
            CompetencyLevel::class,
            $competencyLevel
        );

        $this->assertEquals(
            1,
            $competencyLevel->level
        );

        $this->assertEquals(
            'Beginner',
            $competencyLevel->name
        );

        $this->assertEquals(
            'Basic competency level.',
            $competencyLevel->description
        );

        $this->assertDatabaseHas('competency_levels', [
            'id' => $competencyLevel->id,
            'level' => 1,
            'name' => 'Beginner',
            'description' => 'Basic competency level.',
        ]);
    }

    public function test_it_can_create_competency_level_without_description(): void
    {
        $competencyLevel = $this->competencyLevelService->create([
            'level' => 2,
            'name' => 'Intermediate',
        ]);

        $this->assertInstanceOf(
            CompetencyLevel::class,
            $competencyLevel
        );

        $this->assertNull(
            $competencyLevel->description
        );

        $this->assertDatabaseHas('competency_levels', [
            'id' => $competencyLevel->id,
            'level' => 2,
            'name' => 'Intermediate',
            'description' => null,
        ]);
    }

    public function test_it_can_find_competency_level_by_id(): void
    {
        $competencyLevel = $this->createCompetencyLevel();

        $result = $this->competencyLevelService->findById(
            $competencyLevel->id
        );

        $this->assertInstanceOf(
            CompetencyLevel::class,
            $result
        );

        $this->assertEquals(
            $competencyLevel->id,
            $result->id
        );

        $this->assertEquals(
            1,
            $result->level
        );

        $this->assertEquals(
            'Beginner',
            $result->name
        );
    }

    public function test_it_throws_exception_when_competency_level_is_not_found(): void
    {
        $this->expectException(
            ModelNotFoundException::class
        );

        $this->competencyLevelService->findById(999999);
    }

    public function test_it_can_paginate_competency_levels(): void
    {
        $this->createCompetencyLevel(
            level: 1,
            name: 'Beginner',
        );

        $this->createCompetencyLevel(
            level: 2,
            name: 'Intermediate',
        );

        $this->createCompetencyLevel(
            level: 3,
            name: 'Advanced',
        );

        $result = $this->competencyLevelService->paginate();

        $this->assertCount(
            3,
            $result->items()
        );

        $this->assertEquals(
            3,
            $result->total()
        );
    }

    public function test_it_can_search_competency_level_by_level(): void
    {
        $this->createCompetencyLevel(
            level: 1,
            name: 'Beginner',
        );

        $this->createCompetencyLevel(
            level: 2,
            name: 'Intermediate',
        );

        $result = $this->competencyLevelService->paginate(
            search: '2'
        );

        $this->assertCount(
            1,
            $result->items()
        );

        $this->assertEquals(
            2,
            $result->items()[0]->level
        );
    }

    public function test_it_can_search_competency_level_by_name(): void
    {
        $this->createCompetencyLevel(
            level: 1,
            name: 'Beginner',
        );

        $this->createCompetencyLevel(
            level: 2,
            name: 'Intermediate',
        );

        $result = $this->competencyLevelService->paginate(
            search: 'Intermediate'
        );

        $this->assertCount(
            1,
            $result->items()
        );

        $this->assertEquals(
            'Intermediate',
            $result->items()[0]->name
        );
    }

    public function test_it_can_search_competency_level_by_description(): void
    {
        $this->createCompetencyLevel(
            level: 1,
            name: 'Beginner',
            description: 'Basic competency level.',
        );

        $this->createCompetencyLevel(
            level: 2,
            name: 'Intermediate',
            description: 'Developing competency level.',
        );

        $result = $this->competencyLevelService->paginate(
            search: 'Developing'
        );

        $this->assertCount(
            1,
            $result->items()
        );

        $this->assertEquals(
            2,
            $result->items()[0]->level
        );
    }

    public function test_it_can_update_competency_level(): void
    {
        $competencyLevel = $this->createCompetencyLevel();

        $result = $this->competencyLevelService->update(
            $competencyLevel,
            [
                'level' => 2,
                'name' => 'Intermediate',
                'description' => 'Updated competency level.',
            ]
        );

        $this->assertEquals(
            2,
            $result->level
        );

        $this->assertEquals(
            'Intermediate',
            $result->name
        );

        $this->assertEquals(
            'Updated competency level.',
            $result->description
        );

        $this->assertDatabaseHas('competency_levels', [
            'id' => $competencyLevel->id,
            'level' => 2,
            'name' => 'Intermediate',
            'description' => 'Updated competency level.',
        ]);
    }

    public function test_it_can_delete_competency_level(): void
    {
        $competencyLevel = $this->createCompetencyLevel();

        $this->competencyLevelService->delete(
            $competencyLevel
        );

        $this->assertDatabaseMissing('competency_levels', [
            'id' => $competencyLevel->id,
        ]);
    }
}
