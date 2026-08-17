<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Utility;

enum TestNegativeIntBackedEnum: int
{
    case below = -3;
    case zero = 0;
    case above = 7;
}
