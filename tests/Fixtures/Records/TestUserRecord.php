<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\Repository\Tests\Fixtures\Collections\TestLanguageCollection;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestUserStatus;
use AndyDefer\Repository\Tests\Fixtures\ValueObjects\TestSlug;

final class TestUserRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?TestUserStatus $status = null,
        public readonly ?TestUserRole $role = null,
        public readonly ?TestUserGrade $grade = null,
        public readonly ?TestSlug $slug = null,
        public readonly ?TestLanguageCollection $languages = null,
        public readonly ?ClusterVO $metadata = null,
    ) {}
}
