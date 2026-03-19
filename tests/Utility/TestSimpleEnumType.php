<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Utility;

use PrecisionSoft\Doctrine\Type\Contract\AbstractEnumType;

class TestSimpleEnumType extends AbstractEnumType
{
    public function getEnumClass(): string
    {
        return TestSimpleEnum::class;
    }
}
