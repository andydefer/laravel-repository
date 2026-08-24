<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Fixtures\Models;

use AndyDefer\LaravelCluster\Casts\ClusterCast;
use AndyDefer\Repository\Proxies\AttributeProxy;
use AndyDefer\Repository\Tests\Fixtures\Collections\TestLanguageCollection;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestUserStatus;
use AndyDefer\Repository\Tests\Fixtures\Records\TestUserRecord;
use AndyDefer\Repository\Tests\Fixtures\ValueObjects\TestSlug;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'status',
        'role',
        'grade',
        'slug',        // ✅ AJOUTÉ
        'languages',   // ✅ AJOUTÉ
        'metadata',
        'preferences',
    ];

    protected function casts(): array
    {
        return [
            'status' => TestUserStatus::class,
            'role' => TestUserRole::class,
            'grade' => TestUserGrade::class,
            'preferences' => ClusterCast::class,
            'metadata' => 'array',
            'languages' => 'array',  // ✅ AJOUTÉ
        ];
    }

    /**
     * Get the slug attribute as a TestSlug.
     */
    protected function slug(): Attribute
    {
        return AttributeProxy::nullable(TestSlug::class, column: 'slug');
    }

    /**
     * Get the user_record attribute as a TestUserRecord.
     */
    protected function userRecord(): Attribute
    {
        return AttributeProxy::nullable(TestUserRecord::class, column: 'metadata');
    }

    /**
     * Get the languages attribute as a TestLanguageCollection.
     */
    protected function languages(): Attribute
    {
        return AttributeProxy::nullable(TestLanguageCollection::class, column: 'languages');
    }
}
