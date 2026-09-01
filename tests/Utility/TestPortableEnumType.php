<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Utility;

use PrecisionSoft\Doctrine\Type\Contract\AbstractPortableEnumType;

class TestPortableEnumType extends AbstractPortableEnumType
{
    public static function getDefaultName(): string
    {
        return 'test_portable_enum';
    }

    public function getEnumClass(): string
    {
        return TestBackedEnum::class;
    }
}
