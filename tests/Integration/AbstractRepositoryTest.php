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
use AndyDefer\Repository\Tests\Fixtures\Models\TestProduct;
use AndyDefer\Repository\Tests\Fixtures\Models\TestUser;
use AndyDefer\Repository\Tests\Fixtures\Records\TestProductFilterRecord;
use AndyDefer\Repository\Tests\Fixtures\Records\TestUserFiltersRecord;
use AndyDefer\Repository\Tests\Fixtures\Records\TestUserRecord;
use AndyDefer\Repository\Tests\Fixtures\Repositories\TestProductRepository;
use AndyDefer\Repository\Tests\Fixtures\Repositories\TestUserRepository;
use AndyDefer\Repository\Tests\IntegrationTestCase;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

final class AbstractRepositoryTest extends IntegrationTestCase
{
    private TestUserRepository $userRepository;

    private TestProductRepository $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = new TestUserRepository;
        $this->productRepository = new TestProductRepository;
    }

    // ============================================================================
    // HELPERS
    // ============================================================================

    /**
     * Create a test user with explicit parameters.
     */
    private function createUser(
        ?string $name = null,
        ?string $email = null,
        ?TestUserStatus $status = null,
        ?TestUserRole $role = null,
        ?TestUserGrade $grade = null,
        ?array $metadata = null,
    ): TestUser {
        return TestUser::create([
            'name' => $name ?? 'User '.rand(1, 9999),
            'email' => $email ?? 'user'.rand(1, 9999).'@example.com',
            'status' => $status ?? TestUserStatus::ACTIVE,
            'role' => $role ?? TestUserRole::USER,
            'grade' => $grade ?? TestUserGrade::BRONZE,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create multiple test users.
     */
    private function createUsers(
        int $count,
        ?string $name = null,
        ?string $email = null,
        ?TestUserStatus $status = null,
        ?TestUserRole $role = null,
        ?TestUserGrade $grade = null,
        ?array $metadata = null,
    ): Collection {
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $users[] = $this->createUser(
                name: $name,
                email: $email,
                status: $status,
                role: $role,
                grade: $grade,
                metadata: $metadata,
            );
        }

        return collect($users);
    }

    /**
     * Create a test product with explicit parameters.
     */
    private function createProduct(
        ?string $name = null,
        ?float $price = null,
        ?int $stock = null,
        ?bool $is_active = null,
        ?array $metadata = null,
    ): TestProduct {
        return TestProduct::create([
            'name' => $name ?? 'Product '.rand(1, 9999),
            'price' => $price ?? rand(10, 999) + 0.99,
            'stock' => $stock ?? rand(0, 100),
            'is_active' => $is_active ?? true,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create multiple test products.
     */
    private function createProducts(
        int $count,
        ?string $name = null,
        ?float $price = null,
        ?int $stock = null,
        ?bool $is_active = null,
        ?array $metadata = null,
    ): Collection {
        $products = [];
        for ($i = 0; $i < $count; $i++) {
            $products[] = $this->createProduct(
                name: $name,
                price: $price,
                stock: $stock,
                is_active: $is_active,
                metadata: $metadata,
            );
        }

        return collect($products);
    }

    // ============================================================================
    // CRUD Tests with TestUser (without SoftDeletes)
    // ============================================================================

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
        $user = $this->userRepository->create($record);

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

    // ============================================================================
    // CreateRaw Tests
    // ============================================================================

    public function test_create_raw_creates_model_from_raw_array_data(): void
    {
        // Arrange
        $data = [
            'name' => 'Raw Created User',
            'email' => 'raw@example.com',
            'status' => TestUserStatus::ACTIVE->value,
            'role' => TestUserRole::USER->value,
            'grade' => TestUserGrade::BRONZE->value,
        ];

        // Act
        $user = $this->userRepository->createRaw($data);

        // Assert
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertNotNull($user->id);
        $this->assertSame('Raw Created User', $user->name);
        $this->assertSame('raw@example.com', $user->email);
        $this->assertSame(TestUserStatus::ACTIVE, $user->status);
        $this->assertSame(TestUserRole::USER, $user->role);
        $this->assertSame(TestUserGrade::BRONZE, $user->grade);

        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'name' => 'Raw Created User',
            'email' => 'raw@example.com',
            'status' => TestUserStatus::ACTIVE->value,
            'role' => TestUserRole::USER->value,
            'grade' => TestUserGrade::BRONZE->value,
        ]);
    }

    public function test_create_raw_accepts_null_values_in_array(): void
    {
        // Arrange
        $data = [
            'name' => 'User With Null Email',
            'email' => null,
            'status' => TestUserStatus::ACTIVE->value,
            'role' => TestUserRole::USER->value,
            'grade' => null,
        ];

        // Act
        $user = $this->userRepository->createRaw($data);

        // Assert
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertNotNull($user->id);
        $this->assertSame('User With Null Email', $user->name);
        $this->assertNull($user->email);
        $this->assertSame(TestUserStatus::ACTIVE, $user->status);
        $this->assertNull($user->grade);

        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'name' => 'User With Null Email',
            'email' => null,
            'status' => TestUserStatus::ACTIVE->value,
            'grade' => null,
        ]);
    }

    public function test_create_raw_creates_model_with_minimum_required_fields(): void
    {
        // Arrange
        $data = [
            'name' => 'Minimal User',
            'email' => 'minimal@example.com',
            'status' => TestUserStatus::ACTIVE->value,
            'role' => TestUserRole::USER->value,
            'grade' => TestUserGrade::BRONZE->value,
        ];

        // Act
        $user = $this->userRepository->createRaw($data);

        // Assert
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertNotNull($user->id);
        $this->assertSame('Minimal User', $user->name);
        $this->assertSame('minimal@example.com', $user->email);
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
    }

    public function test_create_raw_returns_created_model_with_auto_incremented_id(): void
    {
        // Arrange
        $this->createUser(name: 'User One', email: 'user1@example.com');
        $this->createUser(name: 'User Two', email: 'user2@example.com');

        // Act
        $users = TestUser::all();

        // Assert
        $this->assertCount(2, $users);
        $this->assertNotSame($users[0]->id, $users[1]->id);
        $this->assertGreaterThan($users[0]->id, $users[1]->id);
    }

    public function test_create_raw_throws_exception_when_required_field_missing(): void
    {
        // Arrange
        $data = [
            'email' => 'missing_name@example.com',
            'status' => TestUserStatus::ACTIVE->value,
            'role' => TestUserRole::USER->value,
            'grade' => TestUserGrade::BRONZE->value,
        ];

        // Assert
        $this->expectException(QueryException::class);

        // Act
        $this->userRepository->createRaw($data);
    }

    public function test_create_raw_with_empty_array_throws_exception(): void
    {
        // Arrange
        $data = [];

        // Assert
        $this->expectException(QueryException::class);

        // Act
        $this->userRepository->createRaw($data);
    }

    public function test_create_raw_for_product_with_soft_deletes_creates_model(): void
    {
        // Arrange
        $data = [
            'name' => 'Raw Created Product',
            'price' => 199.99,
            'stock' => 50,
            'is_active' => true,
        ];

        // Act
        $product = $this->productRepository->createRaw($data);

        // Assert
        $this->assertInstanceOf(TestProduct::class, $product);
        $this->assertNotNull($product->id);
        $this->assertSame('Raw Created Product', $product->name);
        $this->assertSame(199.99, $product->price);
        $this->assertSame(50, $product->stock);
        $this->assertTrue($product->is_active);
        $this->assertNull($product->deleted_at);

        $this->assertDatabaseHas('test_products', [
            'id' => $product->id,
            'name' => 'Raw Created Product',
            'price' => 199.99,
            'stock' => 50,
            'is_active' => 1,
            'deleted_at' => null,
        ]);
    }

    public function test_find_returns_model_when_exists(): void
    {
        // Arrange
        $user = $this->createUser(name: 'Jane Doe', email: 'jane@example.com');

        // Act
        $result = $this->userRepository->find($user->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
        $this->assertSame('Jane Doe', $result->name);
        $this->assertSame('jane@example.com', $result->email);
    }

    public function test_find_returns_null_when_not_exists(): void
    {
        // Act
        $result = $this->userRepository->find(999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_returns_collection_with_filters_and_limit(): void
    {
        // Arrange
        $this->createUsers(count: 2, status: TestUserStatus::ACTIVE);
        $this->createUser(status: TestUserStatus::INACTIVE);

        $filters = new TestUserFiltersRecord(status: TestUserStatus::ACTIVE);
        $columns = new SelectColumns(['id', 'name', 'email', 'status']);

        $findByRecord = new FindByRecord(
            filters: $filters,
            limit: 1,
            sortBy: new SortColumns('name:asc'),
            columns: $columns,
        );

        // Act
        $result = $this->userRepository->findBy($findByRecord);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertStringContainsString('User', $result->first()->name);
    }

    public function test_find_by_returns_all_without_limit_when_limit_is_null(): void
    {
        // Arrange
        $this->createUsers(count: 2);

        $findByRecord = new FindByRecord(
            filters: new EmptyRecord,
            limit: null,
            columns: SelectColumns::all(),
        );

        // Act
        $result = $this->userRepository->findBy($findByRecord);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_by_with_specific_columns_returns_only_selected_columns(): void
    {
        // Arrange
        $this->createUser(name: 'John Doe', email: 'john@example.com');

        $columns = new SelectColumns(['id', 'name']);
        $findByRecord = new FindByRecord(
            filters: new EmptyRecord,
            columns: $columns,
        );

        // Act
        $result = $this->userRepository->findBy($findByRecord);
        $user = $result->first();

        // Assert
        $this->assertNotNull($user->id);
        $this->assertSame('John Doe', $user->name);
        $this->assertNull($user->email);
        $this->assertNull($user->status);
    }

    public function test_find_by_with_multiple_sort_columns_returns_ordered_results(): void
    {
        // Arrange
        $this->createUser(name: 'User A', email: 'user1@example.com');
        $this->createUser(name: 'User A', email: 'user2@example.com');
        $this->createUser(name: 'User B', email: 'user3@example.com');

        $filters = new TestUserFiltersRecord(status: TestUserStatus::ACTIVE);

        $findByRecord = new FindByRecord(
            filters: $filters,
            sortBy: new SortColumns('name:asc|id:desc'),
        );

        // Act
        $result = $this->userRepository->findBy($findByRecord);

        // Assert
        $this->assertCount(3, $result);
        $this->assertSame('User A', $result[0]->name);
        $this->assertSame('User A', $result[1]->name);
        $this->assertSame('User B', $result[2]->name);
    }

    public function test_update_updates_only_non_null_fields_and_returns_updated_model(): void
    {
        // Arrange
        $user = $this->createUser(name: 'Original Name', email: 'original@example.com');

        $updateRecord = new TestUserRecord(name: 'Updated Name');

        // Act
        $updated = $this->userRepository->update($user->id, $updateRecord);

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
        $this->userRepository->update(999, $updateRecord);
    }

    public function test_delete_returns_true_when_user_exists(): void
    {
        // Arrange
        $user = $this->createUser(name: 'To Delete', email: 'delete@example.com');

        // Act
        $result = $this->userRepository->delete($user->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('test_users', ['id' => $user->id]);
    }

    public function test_delete_returns_false_when_user_not_exists(): void
    {
        // Act
        $result = $this->userRepository->delete(999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_count_returns_total_without_criteria(): void
    {
        // Arrange
        $this->createUsers(count: 2);

        // Act
        $count = $this->userRepository->count();

        // Assert
        $this->assertSame(2, $count);
    }

    public function test_count_returns_total_with_criteria(): void
    {
        // Arrange
        $this->createUser(status: TestUserStatus::ACTIVE);
        $this->createUser(status: TestUserStatus::INACTIVE);

        $criteria = new TestUserFiltersRecord(status: TestUserStatus::ACTIVE);

        // Act
        $count = $this->userRepository->count($criteria);

        // Assert
        $this->assertSame(1, $count);
    }

    public function test_exists_returns_true_when_criteria_matches(): void
    {
        // Arrange
        $this->createUser(email: 'exists@example.com');

        $criteria = new TestUserFiltersRecord(email: 'exists@example.com');

        // Act
        $exists = $this->userRepository->exists($criteria);

        // Assert
        $this->assertTrue($exists);
    }

    public function test_exists_returns_false_when_criteria_not_matches(): void
    {
        // Arrange
        $criteria = new TestUserFiltersRecord(email: 'notexists@example.com');

        // Act
        $exists = $this->userRepository->exists($criteria);

        // Assert
        $this->assertFalse($exists);
    }

    public function test_paginate_returns_paginated_results_with_filters_and_sorting(): void
    {
        // Arrange
        $this->createUsers(count: 5, status: TestUserStatus::ACTIVE);
        $this->createUsers(count: 5, status: TestUserStatus::INACTIVE);

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
        $result = $this->userRepository->paginate($paginateRecord);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(3, $result->items());
        $this->assertSame(5, $result->total());
        $this->assertSame(1, $result->currentPage());
    }

    public function test_paginate_with_specific_columns_returns_only_selected_columns(): void
    {
        // Arrange
        $this->createUsers(count: 3);

        $columns = new SelectColumns(['id', 'name']);
        $paginateRecord = new PaginateRecord(
            perPage: 10,
            page: 1,
            columns: $columns,
        );

        // Act
        $result = $this->userRepository->paginate($paginateRecord);
        $user = $result->items()[0];

        // Assert
        $this->assertNotNull($user->id);
        $this->assertStringContainsString('User', $user->name);
        $this->assertNull($user->email);
        $this->assertNull($user->status);
    }

    public function test_delete_bulk_deletes_multiple_models_matching_criteria(): void
    {
        // Arrange
        $this->createUsers(count: 2, status: TestUserStatus::INACTIVE);
        $this->createUser(status: TestUserStatus::ACTIVE, email: 'keep@example.com');

        $criteria = new TestUserFiltersRecord(status: TestUserStatus::INACTIVE);

        // Act
        $deletedCount = $this->userRepository->deleteBulk($criteria);

        // Assert
        $this->assertSame(2, $deletedCount);
        $this->assertDatabaseCount('test_users', 1);
        $this->assertDatabaseHas('test_users', ['email' => 'keep@example.com']);
    }

    public function test_update_raw_updates_with_raw_data_including_null_values(): void
    {
        // Arrange
        $user = $this->createUser(name: 'Original Name', email: 'original@example.com');

        // Act
        $updated = $this->userRepository->updateRaw($user->id, [
            'name' => 'Updated Name',
            'email' => null,
            'status' => TestUserStatus::INACTIVE->value,
        ]);

        // Assert
        $this->assertSame('Updated Name', $updated->name);
        $this->assertNull($updated->email);
        $this->assertSame(TestUserStatus::INACTIVE, $updated->status);

        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'status' => TestUserStatus::INACTIVE->value,
        ]);
    }

    public function test_update_raw_updates_only_provided_fields_when_passed_partial_data(): void
    {
        // Arrange
        $user = $this->createUser(name: 'Original Name', email: 'original@example.com');

        // Act
        $updated = $this->userRepository->updateRaw($user->id, ['name' => 'Updated Name']);

        // Assert
        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('original@example.com', $updated->email);

        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'original@example.com',
        ]);
    }

    public function test_update_raw_throws_exception_when_user_not_found(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AndyDefer\\Repository\\Tests\\Fixtures\\Models\\TestUser with id 999 not found');

        // Act
        $this->userRepository->updateRaw(999, ['name' => 'New Name']);
    }

    public function test_update_raw_with_empty_data_does_nothing_and_returns_model(): void
    {
        // Arrange
        $user = $this->createUser(name: 'Original Name', email: 'original@example.com');

        // Act
        $updated = $this->userRepository->updateRaw($user->id, []);

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

    // ============================================================================
    // SoftDelete Tests with TestProduct (with SoftDeletes)
    // ============================================================================

    public function test_soft_delete_restore_recovers_soft_deleted_model(): void
    {
        // Arrange
        $product = $this->createProduct(name: 'Test Product');

        // Act - Soft delete
        $deleted = $this->productRepository->delete($product->id);
        $this->assertTrue($deleted);

        $this->assertDatabaseHas('test_products', ['id' => $product->id]);
        $this->assertDatabaseMissing('test_products', ['id' => $product->id, 'deleted_at' => null]);

        $this->assertNull($this->productRepository->find($product->id));

        // Act - Restore
        $restored = $this->productRepository->restore($product->id);

        // Assert
        $this->assertTrue($restored);

        $restoredProduct = $this->productRepository->find($product->id);
        $this->assertNotNull($restoredProduct);
        $this->assertSame('Test Product', $restoredProduct->name);

        $this->assertDatabaseHas('test_products', ['id' => $product->id, 'deleted_at' => null]);
    }

    public function test_soft_delete_restore_returns_false_when_model_not_found(): void
    {
        // Act
        $restored = $this->productRepository->restore(999);

        // Assert
        $this->assertFalse($restored);
    }

    public function test_soft_delete_restore_returns_false_when_model_is_not_soft_deleted(): void
    {
        // Arrange
        $product = $this->createProduct(name: 'Not Deleted');

        // Act
        $restored = $this->productRepository->restore($product->id);

        // Assert
        $this->assertFalse($restored);
    }

    public function test_find_with_trashed_returns_soft_deleted_model(): void
    {
        // Arrange
        $product = $this->createProduct(name: 'Hidden Product');
        $this->productRepository->delete($product->id);

        // Act
        $found = $this->productRepository->findWithTrashed($product->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertSame('Hidden Product', $found->name);
        $this->assertNotNull($found->deleted_at);
    }

    public function test_find_with_trashed_returns_null_when_not_found(): void
    {
        // Act
        $found = $this->productRepository->findWithTrashed(999);

        // Assert
        $this->assertNull($found);
    }

    public function test_force_delete_permanently_removes_model(): void
    {
        // Arrange
        $product = $this->createProduct(name: 'To Force Delete');

        // Act
        $deleted = $this->productRepository->forceDelete($product->id);

        // Assert
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('test_products', ['id' => $product->id]);
    }

    public function test_force_delete_returns_false_when_model_not_found(): void
    {
        // Act
        $deleted = $this->productRepository->forceDelete(999);

        // Assert
        $this->assertFalse($deleted);
    }

    public function test_find_by_with_trashed_filter_returns_deleted_records(): void
    {
        // Arrange
        $this->createProducts(count: 2, name: 'Active Product', is_active: true);

        $deletedProduct = $this->createProduct(name: 'Deleted Product', is_active: false);
        $this->productRepository->delete($deletedProduct->id);

        $filters = new TestProductFilterRecord(is_deleted: true);

        // Act
        $result = $this->productRepository->count($filters);

        // Assert
        $this->assertSame(1, $result);
    }

    public function test_find_by_without_trashed_filter_excludes_deleted_records(): void
    {
        // Arrange
        $this->createProduct(name: 'Active Product', is_active: true);

        $deletedProduct = $this->createProduct(name: 'Deleted Product', is_active: false);
        $this->productRepository->delete($deletedProduct->id);

        $filters = new TestProductFilterRecord(is_deleted: false);

        // Act
        $result = $this->productRepository->count($filters);

        // Assert
        $this->assertSame(1, $result);
    }

    public function test_force_delete_bulk_permanently_removes_soft_deleted_models(): void
    {
        // Arrange
        $product1 = $this->createProduct(name: 'To Force Delete 1');
        $product2 = $this->createProduct(name: 'To Force Delete 2');
        $product3 = $this->createProduct(name: 'Keep');

        // Soft delete les deux premiers
        $this->productRepository->delete($product1->id);
        $this->productRepository->delete($product2->id);

        $this->assertDatabaseHas('test_products', ['id' => $product1->id]);
        $this->assertDatabaseHas('test_products', ['id' => $product2->id]);
        $this->assertDatabaseMissing('test_products', ['id' => $product1->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('test_products', ['id' => $product2->id, 'deleted_at' => null]);

        $filters = new TestProductFilterRecord(is_deleted: true);

        // Act
        $deletedCount = $this->productRepository->forceDeleteBulk($filters);

        // Assert
        $this->assertSame(2, $deletedCount);
        $this->assertDatabaseMissing('test_products', ['id' => $product1->id]);
        $this->assertDatabaseMissing('test_products', ['id' => $product2->id]);
        $this->assertDatabaseHas('test_products', ['id' => $product3->id]);
    }

    public function test_force_delete_bulk_on_model_without_soft_deletes_performs_hard_delete(): void
    {
        // Arrange
        $user1 = $this->createUser(name: 'To Delete 1', status: TestUserStatus::INACTIVE);
        $user2 = $this->createUser(name: 'To Delete 2', status: TestUserStatus::INACTIVE);
        $user3 = $this->createUser(name: 'Keep', status: TestUserStatus::ACTIVE, email: 'keep@example.com');

        $criteria = new TestUserFiltersRecord(status: TestUserStatus::INACTIVE);

        // Act
        $deletedCount = $this->userRepository->forceDeleteBulk($criteria);

        // Assert
        $this->assertSame(2, $deletedCount);
        $this->assertDatabaseMissing('test_users', ['id' => $user1->id]);
        $this->assertDatabaseMissing('test_users', ['id' => $user2->id]);
        $this->assertDatabaseHas('test_users', ['email' => 'keep@example.com']);
    }

    // ============================================================================
    // ClusterFilter Tests
    // ============================================================================
    public function test_where_cluster_filters_results(): void
    {
        // Arrange
        $this->createUser(
            name: 'User 1',
            email: 'user1@example.com',
            metadata: ['status' => 'active', 'role' => 'admin']
        );

        $this->createUser(
            name: 'User 2',
            email: 'user2@example.com',
            metadata: ['status' => 'inactive', 'role' => 'doctor']
        );

        $this->createUser(
            name: 'User 3',
            email: 'user3@example.com',
            metadata: ['status' => 'active', 'role' => 'doctor']
        );

        // Act
        $repository = $this->userRepository
            ->whereCluster('metadata', 'status=active & role=admin')
            ->findBy(new FindByRecord(
                filters: new EmptyRecord,
                columns: SelectColumns::all(),
            ));

        // Assert
        $this->assertCount(1, $repository);
        $this->assertSame('User 1', $repository->first()->name);
    }

    public function test_where_cluster_with_complex_query(): void
    {
        // Arrange
        $this->createUser(
            name: 'User 1',
            email: 'user1@example.com',
            metadata: [
                'status' => 'active',
                'role' => 'admin',
                'age' => 30,
                'addresses' => [
                    ['city' => 'Kinshasa', 'country' => 'RDC'],
                    ['city' => 'Paris', 'country' => 'France'],
                ],
            ]
        );

        $this->createUser(
            name: 'User 2',
            email: 'user2@example.com',
            metadata: [
                'status' => 'active',
                'role' => 'doctor',
                'age' => 25,
                'addresses' => [
                    ['city' => 'Paris', 'country' => 'France'],
                ],
            ]
        );

        $this->createUser(
            name: 'User 3',
            email: 'user3@example.com',
            metadata: [
                'status' => 'active',
                'role' => 'admin',
                'age' => 35,
                'addresses' => [
                    ['city' => 'Kinshasa', 'country' => 'RDC'],
                    ['city' => 'London', 'country' => 'UK'],
                    ['city' => 'Paris', 'country' => 'France'],
                ],
            ]
        );

        // Act - Sous-condition + condition
        $repository = $this->userRepository
            ->whereCluster('metadata', 'status=active & addresses[city=Kinshasa]')
            ->findBy(new FindByRecord(
                filters: new EmptyRecord,
                columns: SelectColumns::all(),
            ));

        // Assert
        $this->assertCount(2, $repository);
        $names = $repository->pluck('name')->toArray();
        $this->assertContains('User 1', $names);
        $this->assertContains('User 3', $names);
    }

    public function test_where_cluster_with_aggregate_function(): void
    {
        // Arrange
        $this->createUser(
            name: 'User 1',
            email: 'user1@example.com',
            metadata: [
                'addresses' => ['a', 'b', 'c'],
                'scores' => [80, 90, 85],
            ]
        );

        $this->createUser(
            name: 'User 2',
            email: 'user2@example.com',
            metadata: [
                'addresses' => ['a', 'b'],
                'scores' => [70, 75, 80],
            ]
        );

        // Act
        $repository = $this->userRepository
            ->whereCluster('metadata', 'COUNT(addresses) > 2')
            ->findBy(new FindByRecord(
                filters: new EmptyRecord,
                columns: SelectColumns::all(),
            ));

        // Assert
        $this->assertCount(1, $repository);
        $this->assertSame('User 1', $repository->first()->name);
    }

    public function test_where_cluster_chaining_multiple_filters(): void
    {
        // Arrange
        $this->createUser(
            name: 'User 1',
            email: 'user1@example.com',
            metadata: ['status' => 'active', 'role' => 'admin', 'age' => 30]
        );

        $this->createUser(
            name: 'User 2',
            email: 'user2@example.com',
            metadata: ['status' => 'active', 'role' => 'doctor', 'age' => 25]
        );

        $this->createUser(
            name: 'User 3',
            email: 'user3@example.com',
            metadata: ['status' => 'active', 'role' => 'admin', 'age' => 35]
        );

        // Act - Chaînage multiple
        $repository = $this->userRepository
            ->whereCluster('metadata', 'status=active')
            ->whereCluster('metadata', 'role=admin')
            ->whereCluster('metadata', 'age>=30')
            ->findBy(new FindByRecord(
                filters: new EmptyRecord,
                columns: SelectColumns::all(),
            ));

        // Assert
        $this->assertCount(2, $repository);
        $names = $repository->pluck('name')->toArray();
        $this->assertContains('User 1', $names);
        $this->assertContains('User 3', $names);
    }

    public function test_where_cluster_with_find(): void
    {
        // Arrange
        $user = $this->createUser(
            name: 'Test User',
            email: 'test@example.com',
            metadata: ['status' => 'active', 'role' => 'admin']
        );

        // Act
        $found = $this->userRepository
            ->whereCluster('metadata', 'role=admin')
            ->find($user->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);
        $this->assertSame('Test User', $found->name);
    }

    public function test_where_cluster_with_count(): void
    {
        // Arrange
        $this->createUser(
            name: 'User 1',
            email: 'user1@example.com',
            metadata: ['status' => 'active']
        );

        $this->createUser(
            name: 'User 2',
            email: 'user2@example.com',
            metadata: ['status' => 'inactive']
        );

        $this->createUser(
            name: 'User 3',
            email: 'user3@example.com',
            metadata: ['status' => 'active']
        );

        // Act
        $count = $this->userRepository
            ->whereCluster('metadata', 'status=active')
            ->count();

        // Assert
        $this->assertSame(2, $count);
    }

    public function test_where_cluster_with_paginate(): void
    {
        // Arrange
        for ($i = 1; $i <= 10; $i++) {
            $this->createUser(
                name: "User {$i}",
                email: "user{$i}@example.com",
                metadata: ['status' => $i <= 5 ? 'active' : 'inactive']
            );
        }

        // Act
        $result = $this->userRepository
            ->whereCluster('metadata', 'status=active')
            ->paginate(new PaginateRecord(
                perPage: 3,
                page: 1,
                filters: new EmptyRecord,
                columns: SelectColumns::all(),
            ));

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(3, $result->items());
        $this->assertSame(5, $result->total());
        $this->assertSame(1, $result->currentPage());
    }

    public function test_where_cluster_clear_filters(): void
    {
        // Arrange
        $this->createUser(
            name: 'User 1',
            email: 'user1@example.com',
            metadata: ['status' => 'active']
        );

        $this->createUser(
            name: 'User 2',
            email: 'user2@example.com',
            metadata: ['status' => 'inactive']
        );

        // Act - Avec filtre
        $filtered = $this->userRepository
            ->whereCluster('metadata', 'status=active')
            ->count();

        $this->assertSame(1, $filtered);

        // Act - Clear puis count
        $all = $this->userRepository
            ->clearClusterFilters()
            ->count();

        // Assert
        $this->assertSame(2, $all);
    }

    public function test_where_cluster_with_existing_filters(): void
    {
        // Arrange
        $this->createUser(
            name: 'User 1',
            email: 'user1@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::ADMIN,
            metadata: ['status' => 'active', 'role' => 'admin']
        );

        $this->createUser(
            name: 'User 2',
            email: 'user2@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::USER,
            metadata: ['status' => 'active', 'role' => 'user']
        );

        // Act - Filtres Record + Cluster
        $filters = new TestUserFiltersRecord(role: TestUserRole::ADMIN);
        $result = $this->userRepository
            ->whereCluster('metadata', 'status=active')
            ->findBy(new FindByRecord(
                filters: $filters,
                columns: SelectColumns::all(),
            ));

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame('User 1', $result->first()->name);
    }

    public function test_where_cluster_with_or_condition(): void
    {
        // Arrange
        $this->createUser(
            name: 'User 1',
            email: 'user1@example.com',
            metadata: ['status' => 'active', 'role' => 'admin']
        );

        $this->createUser(
            name: 'User 2',
            email: 'user2@example.com',
            status: TestUserStatus::SUSPENDED,
            metadata: ['status' => 'suspended', 'role' => 'guest']
        );

        $this->createUser(
            name: 'User 3',
            email: 'user3@example.com',
            metadata: ['status' => 'active', 'role' => 'doctor']
        );

        // Act
        $result = $this->userRepository
            ->whereCluster('metadata', 'status=active | status=suspended')
            ->findBy(new FindByRecord(
                filters: new EmptyRecord,
                columns: SelectColumns::all(),
            ));

        // Assert
        $this->assertCount(3, $result);
    }
}
