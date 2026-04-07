<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Utility;

use PrecisionSoft\Doctrine\Type\Contract\AbstractSetType;

class TestSimpleSetType extends AbstractSetType
{
    public function getEnumClass(): ?string
    {
        return TestSimpleEnum::class;
    }
}
