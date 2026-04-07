<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Utility;

enum TestIntBackedEnum: int
{
    case low = 1;
    case medium = 5;
    case high = 10;
}
