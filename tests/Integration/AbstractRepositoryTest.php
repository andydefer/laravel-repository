<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Integration;

use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\Repository\Enums\SortDirection;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestUserStatus;
use AndyDefer\Repository\Tests\Fixtures\Models\TestUser;
use AndyDefer\Repository\Tests\Fixtures\Records\TestUserFiltersRecord;
use AndyDefer\Repository\Tests\Fixtures\Records\TestUserRecord;
use AndyDefer\Repository\Tests\Fixtures\Repositories\TestUserRepository;
use AndyDefer\Repository\Tests\IntegrationTestCase;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class AbstractRepositoryTest extends IntegrationTestCase
{
    private TestUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TestUserRepository;
    }

    public function test_create_returns_model_and_persists_to_database(): void
    {
        // Arrange
        $record = new TestUserRecord(
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::USER,
            grade: TestUserGrade::BRONZE,
        );

        // Act
        $user = $this->repository->create($record);

        // Assert
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertNotNull($user->id);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('john@example.com', $user->email);
        $this->assertSame(TestUserStatus::ACTIVE, $user->status);
        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => TestUserStatus::ACTIVE->value,
            'role' => TestUserRole::USER->value,
            'grade' => TestUserGrade::BRONZE->value,
        ]);
    }

    public function test_find_returns_model_when_exists(): void
    {
        // Arrange
        $user = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        // Act
        $result = $this->repository->find($user->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
        $this->assertSame('Jane Doe', $result->name);
        $this->assertSame('jane@example.com', $result->email);
    }

    public function test_find_returns_null_when_not_exists(): void
    {
        // Act
        $result = $this->repository->find(999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_returns_collection_with_filters_and_limit(): void
    {
        // Arrange
        TestUser::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'status' => TestUserStatus::INACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $filters = new TestUserFiltersRecord(status: TestUserStatus::ACTIVE);
        $columns = new SelectColumns(['id', 'name', 'email', 'status']);

        $findByRecord = new FindByRecord(
            filters: $filters,
            limit: 1,
            sortBy: 'name',
            sortDir: SortDirection::ASC,
            columns: $columns,
        );

        // Act
        $result = $this->repository->findBy($findByRecord);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result->first()->name);
    }

    public function test_find_by_returns_all_without_limit_when_limit_is_null(): void
    {
        // Arrange
        TestUser::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $findByRecord = new FindByRecord(
            filters: new EmptyRecord,
            limit: null,
            columns: SelectColumns::all(),
        );

        // Act
        $result = $this->repository->findBy($findByRecord);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_by_with_specific_columns_returns_only_selected_columns(): void
    {
        // Arrange
        TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $columns = new SelectColumns(['id', 'name']);
        $findByRecord = new FindByRecord(
            filters: new EmptyRecord,
            columns: $columns,
        );

        // Act
        $result = $this->repository->findBy($findByRecord);
        $user = $result->first();

        // Assert
        $this->assertNotNull($user->id);
        $this->assertSame('John Doe', $user->name);
        $this->assertNull($user->email);
        $this->assertNull($user->status);
    }

    public function test_update_updates_only_non_null_fields_and_returns_updated_model(): void
    {
        // Arrange
        $user = TestUser::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $updateRecord = new TestUserRecord(
            name: 'Updated Name',
        );

        // Act
        $updated = $this->repository->update($user->id, $updateRecord);

        // Assert
        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('original@example.com', $updated->email);
        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'original@example.com',
        ]);
    }

    public function test_update_throws_exception_when_user_not_found(): void
    {
        // Arrange
        $updateRecord = new TestUserRecord(name: 'New Name');

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AndyDefer\\Repository\\Tests\\Fixtures\\Models\\TestUser with id 999 not found');

        // Act
        $this->repository->update(999, $updateRecord);
    }

    public function test_delete_returns_true_when_user_exists(): void
    {
        // Arrange
        $user = TestUser::create([
            'name' => 'To Delete',
            'email' => 'delete@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        // Act
        $result = $this->repository->delete($user->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('test_users', ['id' => $user->id]);
    }

    public function test_delete_returns_false_when_user_not_exists(): void
    {
        // Act
        $result = $this->repository->delete(999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_count_returns_total_without_criteria(): void
    {
        // Arrange
        TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'status' => TestUserStatus::INACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        // Act
        $count = $this->repository->count();

        // Assert
        $this->assertSame(2, $count);
    }

    public function test_count_returns_total_with_criteria(): void
    {
        // Arrange
        TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'status' => TestUserStatus::INACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $criteria = new TestUserFiltersRecord(status: TestUserStatus::ACTIVE);

        // Act
        $count = $this->repository->count($criteria);

        // Assert
        $this->assertSame(1, $count);
    }

    public function test_exists_returns_true_when_criteria_matches(): void
    {
        // Arrange
        TestUser::create([
            'name' => 'Existing User',
            'email' => 'exists@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $criteria = new TestUserFiltersRecord(email: 'exists@example.com');

        // Act
        $exists = $this->repository->exists($criteria);

        // Assert
        $this->assertTrue($exists);
    }

    public function test_exists_returns_false_when_criteria_not_matches(): void
    {
        // Arrange
        $criteria = new TestUserFiltersRecord(email: 'notexists@example.com');

        // Act
        $exists = $this->repository->exists($criteria);

        // Assert
        $this->assertFalse($exists);
    }

    public function test_paginate_returns_paginated_results_with_filters_and_sorting(): void
    {
        // Arrange
        for ($i = 1; $i <= 10; $i++) {
            TestUser::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'status' => $i <= 5 ? TestUserStatus::ACTIVE : TestUserStatus::INACTIVE,
                'role' => TestUserRole::USER,
                'grade' => TestUserGrade::BRONZE,
            ]);
        }

        $filters = new TestUserFiltersRecord(status: TestUserStatus::ACTIVE);
        $columns = new SelectColumns(['id', 'name', 'email', 'status']);

        $paginateRecord = new PaginateRecord(
            perPage: 3,
            page: 1,
            sortBy: 'name',
            sortDir: SortDirection::ASC,
            filters: $filters,
            columns: $columns,
        );

        // Act
        /** @var Collection|\Countable $result */
        $result = $this->repository->paginate($paginateRecord);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(3, $result->count());
        $this->assertSame(5, $result->total());
        $this->assertSame(1, $result->currentPage());

        $items = $result->items();
        $this->assertSame('User 1', $items[0]->name);
    }

    public function test_paginate_with_specific_columns_returns_only_selected_columns(): void
    {
        // Arrange
        for ($i = 1; $i <= 3; $i++) {
            TestUser::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'status' => TestUserStatus::ACTIVE,
                'role' => TestUserRole::USER,
                'grade' => TestUserGrade::BRONZE,
            ]);
        }

        $columns = new SelectColumns(['id', 'name']);
        $paginateRecord = new PaginateRecord(
            perPage: 10,
            page: 1,
            columns: $columns,
        );

        // Act
        $result = $this->repository->paginate($paginateRecord);
        $user = $result->items()[0];

        // Assert
        $this->assertNotNull($user->id);
        $this->assertSame('User 1', $user->name);
        $this->assertNull($user->email);
        $this->assertNull($user->status);
    }

    public function test_delete_bulk_deletes_multiple_models_matching_criteria(): void
    {
        // Arrange
        TestUser::create([
            'name' => 'To Delete 1',
            'email' => 'todelete1@example.com',
            'status' => TestUserStatus::INACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'To Delete 2',
            'email' => 'todelete2@example.com',
            'status' => TestUserStatus::INACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'Keep',
            'email' => 'keep@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $criteria = new TestUserFiltersRecord(status: TestUserStatus::INACTIVE);

        // Act
        $deletedCount = $this->repository->deleteBulk($criteria);

        // Assert
        $this->assertSame(2, $deletedCount);
        $this->assertDatabaseCount('test_users', 1);
        $this->assertDatabaseHas('test_users', ['email' => 'keep@example.com']);
        $this->assertDatabaseMissing('test_users', ['email' => 'todelete1@example.com']);
        $this->assertDatabaseMissing('test_users', ['email' => 'todelete2@example.com']);
    }

    public function test_update_raw_updates_with_raw_data_including_null_values(): void
    {
        // Arrange
        $user = TestUser::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        // Act
        $updated = $this->repository->updateRaw($user->id, [
            'name' => 'Updated Name',
            'email' => null,
            'status' => TestUserStatus::INACTIVE->value,
        ]);

        // Assert
        $this->assertSame('Updated Name', $updated->name);
        $this->assertNull($updated->email);
        $this->assertSame(TestUserStatus::INACTIVE, $updated->status);
        $this->assertSame(TestUserRole::USER, $updated->role);
        $this->assertSame(TestUserGrade::BRONZE, $updated->grade);

        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'status' => TestUserStatus::INACTIVE->value,
            'role' => TestUserRole::USER->value,
            'grade' => TestUserGrade::BRONZE->value,
        ]);
    }

    public function test_update_raw_updates_only_provided_fields_when_passed_partial_data(): void
    {
        // Arrange
        $user = TestUser::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        // Act
        $updated = $this->repository->updateRaw($user->id, [
            'name' => 'Updated Name',
        ]);

        // Assert
        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('original@example.com', $updated->email);
        $this->assertSame(TestUserStatus::ACTIVE, $updated->status);
        $this->assertSame(TestUserRole::USER, $updated->role);
        $this->assertSame(TestUserGrade::BRONZE, $updated->grade);

        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'original@example.com',
            'status' => TestUserStatus::ACTIVE->value,
        ]);
    }

    public function test_update_raw_sets_null_on_explicitly_provided_null_values(): void
    {
        // Arrange
        $user = TestUser::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
            'metadata' => json_encode(['key' => 'value']),
        ]);

        // Act
        $updated = $this->repository->updateRaw($user->id, [
            'metadata' => null,
        ]);

        // Assert
        $this->assertNull($updated->metadata);

        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'metadata' => null,
        ]);
    }

    public function test_update_raw_throws_exception_when_user_not_found(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AndyDefer\\Repository\\Tests\\Fixtures\\Models\\TestUser with id 999 not found');

        // Act
        $this->repository->updateRaw(999, ['name' => 'New Name']);
    }

    public function test_update_raw_with_empty_data_does_nothing_and_returns_model(): void
    {
        // Arrange
        $user = TestUser::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        // Act
        $updated = $this->repository->updateRaw($user->id, []);

        // Assert
        $this->assertSame($user->id, $updated->id);
        $this->assertSame('Original Name', $updated->name);
        $this->assertSame('original@example.com', $updated->email);

        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);
    }
}
