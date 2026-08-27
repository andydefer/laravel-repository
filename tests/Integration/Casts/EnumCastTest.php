<?php

// tests/Integration/Casts/EnumCastTest.php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Integration\Casts;

use AndyDefer\Repository\Configs\RepositoryConfig;
use AndyDefer\Repository\Contracts\Configs\RepositoryConfigInterface;
use AndyDefer\Repository\Contracts\EnumerableInterface;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestProductStatus;
use AndyDefer\Repository\Tests\Fixtures\Models\TestProduct;
use AndyDefer\Repository\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\Config;

final class EnumCastTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('repository.enum_casts', [
            'test_products' => [
                'status' => TestProductStatus::class,
            ],
        ]);

        $this->app->singleton(RepositoryConfig::class, function ($app) {
            return new RepositoryConfig($app['config']);
        });

        $this->app->bind(RepositoryConfigInterface::class, RepositoryConfig::class);
    }

    public function test_cast_returns_enum_from_string(): void
    {
        $product = TestProduct::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
            'status' => 'published',
            'is_active' => true,
        ]);

        $fresh = TestProduct::find($product->id);

        $this->assertInstanceOf(EnumerableInterface::class, $fresh->status);
        $this->assertInstanceOf(TestProductStatus::class, $fresh->status);
        $this->assertSame(TestProductStatus::PUBLISHED, $fresh->status);
        $this->assertSame('published', $fresh->status->getValue());
        $this->assertSame('Publié', $fresh->status->getLabel());
    }

    public function test_cast_handles_enum_directly(): void
    {
        $product = TestProduct::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
            'status' => TestProductStatus::ARCHIVED,
            'is_active' => true,
        ]);

        $fresh = TestProduct::find($product->id);

        $this->assertInstanceOf(EnumerableInterface::class, $fresh->status);
        $this->assertSame(TestProductStatus::ARCHIVED, $fresh->status);
        $this->assertSame('archived', $fresh->status->getValue());
        $this->assertSame('Archivé', $fresh->status->getLabel());
    }

    public function test_stores_enum_as_string_in_database(): void
    {
        $product = TestProduct::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
            'status' => TestProductStatus::OUT_OF_STOCK,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('test_products', [
            'id' => $product->id,
            'status' => 'out_of_stock',
        ]);

        $raw = TestProduct::find($product->id)->getAttributes();
        $this->assertIsString($raw['status']);
        $this->assertSame('out_of_stock', $raw['status']);
    }

    public function test_stores_string_value_in_database(): void
    {
        $product = TestProduct::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
            'status' => 'draft',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('test_products', [
            'id' => $product->id,
            'status' => 'draft',
        ]);

        $fresh = TestProduct::find($product->id);
        $this->assertSame(TestProductStatus::DRAFT, $fresh->status);
    }

    public function test_handles_all_enum_values(): void
    {
        $statuses = [
            'draft' => ['label' => 'Brouillon'],
            'published' => ['label' => 'Publié'],
            'archived' => ['label' => 'Archivé'],
            'out_of_stock' => ['label' => 'Rupture de stock'],
        ];

        foreach ($statuses as $value => $expected) {
            $product = TestProduct::create([
                'name' => "Product {$value}",
                'price' => 99.99,
                'stock' => 10,
                'status' => $value,
                'is_active' => true,
            ]);

            $fresh = TestProduct::find($product->id);

            $this->assertInstanceOf(EnumerableInterface::class, $fresh->status);
            $this->assertSame($value, $fresh->status->getValue());
            $this->assertSame($expected['label'], $fresh->status->getLabel());
        }
    }

    public function test_update_status_via_enum(): void
    {
        $product = TestProduct::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
            'status' => TestProductStatus::DRAFT,
            'is_active' => true,
        ]);

        $product->status = TestProductStatus::PUBLISHED;
        $product->save();

        $fresh = TestProduct::find($product->id);

        $this->assertInstanceOf(EnumerableInterface::class, $fresh->status);
        $this->assertSame(TestProductStatus::PUBLISHED, $fresh->status);
        $this->assertSame('published', $fresh->status->getValue());

        $this->assertDatabaseHas('test_products', [
            'id' => $product->id,
            'status' => 'published',
        ]);
    }

    public function test_update_status_via_string(): void
    {
        $product = TestProduct::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
            'status' => TestProductStatus::DRAFT,
            'is_active' => true,
        ]);

        $product->status = 'archived';
        $product->save();

        $fresh = TestProduct::find($product->id);

        $this->assertInstanceOf(EnumerableInterface::class, $fresh->status);
        $this->assertSame(TestProductStatus::ARCHIVED, $fresh->status);
        $this->assertSame('archived', $fresh->status->getValue());

        $this->assertDatabaseHas('test_products', [
            'id' => $product->id,
            'status' => 'archived',
        ]);
    }

    public function test_handles_null_value(): void
    {
        $product = TestProduct::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
            'status' => null,
            'is_active' => true,
        ]);

        $fresh = TestProduct::find($product->id);
        $this->assertNull($fresh->status);
        $this->assertNull($fresh->getAttributes()['status']);
    }

    public function test_handles_default_value(): void
    {
        $product = TestProduct::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
            'is_active' => true,
        ]);

        $fresh = TestProduct::find($product->id);

        $this->assertInstanceOf(EnumerableInterface::class, $fresh->status);
        $this->assertSame(TestProductStatus::DRAFT, $fresh->status);
        $this->assertSame('draft', $fresh->status->getValue());
    }

    public function test_cast_works_with_soft_delete(): void
    {
        $product = TestProduct::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
            'status' => TestProductStatus::PUBLISHED,
            'is_active' => true,
        ]);

        $product->delete();

        $fresh = TestProduct::withTrashed()->find($product->id);

        $this->assertInstanceOf(EnumerableInterface::class, $fresh->status);
        $this->assertSame(TestProductStatus::PUBLISHED, $fresh->status);
        $this->assertNotNull($fresh->deleted_at);
    }

    public function test_cast_with_multiple_records(): void
    {
        TestProduct::create([
            'name' => 'Product 1',
            'price' => 99.99,
            'stock' => 10,
            'status' => TestProductStatus::PUBLISHED,
            'is_active' => true,
        ]);

        TestProduct::create([
            'name' => 'Product 2',
            'price' => 49.99,
            'stock' => 5,
            'status' => TestProductStatus::DRAFT,
            'is_active' => true,
        ]);

        TestProduct::create([
            'name' => 'Product 3',
            'price' => 149.99,
            'stock' => 0,
            'status' => TestProductStatus::OUT_OF_STOCK,
            'is_active' => true,
        ]);

        $products = TestProduct::all();
        $this->assertCount(3, $products);

        $statuses = $products->pluck('status')->map(fn ($status) => $status->getValue())->toArray();
        $this->assertContains('published', $statuses);
        $this->assertContains('draft', $statuses);
        $this->assertContains('out_of_stock', $statuses);

        foreach ($products as $product) {
            $this->assertInstanceOf(EnumerableInterface::class, $product->status);
        }
    }

    public function test_cast_performance_with_many_records(): void
    {
        $statuses = TestProductStatus::cases();

        for ($i = 1; $i <= 50; $i++) {
            TestProduct::create([
                'name' => "Product $i",
                'price' => rand(10, 500),
                'stock' => rand(0, 100),
                'status' => $statuses[array_rand($statuses)],
                'is_active' => true,
            ]);
        }

        $start = microtime(true);

        $products = TestProduct::all();

        foreach ($products as $product) {
            $this->assertInstanceOf(EnumerableInterface::class, $product->status);
        }

        $end = microtime(true);
        $time = ($end - $start) * 1000;

        $this->assertLessThan(100, $time);
    }

    public function test_throws_exception_for_invalid_enum_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid enum value for table "test_products", column "status"/');

        $product = new TestProduct;
        $product->status = 'invalid_status';
        $product->save();
    }

    public function test_throws_exception_for_invalid_enum_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid enum value for table "test_products", column "status"/');

        $product = new TestProduct;
        $product->status = ['invalid'];
        $product->save();
    }
}
