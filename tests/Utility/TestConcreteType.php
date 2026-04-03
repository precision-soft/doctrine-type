<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Utility;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use PrecisionSoft\Doctrine\Type\Contract\AbstractType;

class TestConcreteType extends AbstractType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TEST';
    }
}
