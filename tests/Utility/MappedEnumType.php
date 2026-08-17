<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Utility;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use PrecisionSoft\Doctrine\Type\Contract\AbstractEnumType;

/** @internal */
class MappedEnumType extends AbstractEnumType
{
    public function getEnumClass(): ?string
    {
        return TestBackedEnum::class;
    }

    /** @return array<int, string> */
    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return ['enum'];
    }
}
