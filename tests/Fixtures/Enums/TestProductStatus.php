<?php

// tests/Fixtures/Enums/TestProductStatus.php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Fixtures\Enums;

use AndyDefer\Repository\Contracts\EnumerableInterface;

enum TestProductStatus: string implements EnumerableInterface
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
    case OUT_OF_STOCK = 'out_of_stock';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::PUBLISHED => 'Publié',
            self::ARCHIVED => 'Archivé',
            self::OUT_OF_STOCK => 'Rupture de stock',
        };
    }
}
