<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Fixtures\Enums;

enum TestUserGrade: int
{
    case BRONZE = 1;
    case SILVER = 2;
    case GOLD = 3;
    case PLATINUM = 4;
}
