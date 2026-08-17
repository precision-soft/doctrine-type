<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeType as DoctrineDateTimeType;

class DateTimeType extends DoctrineDateTimeType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $sqlDeclaration = parent::getSQLDeclaration($column, $platform);

        /* strict `true` only: a truthy `1` in the column options deliberately does not enable the clause */
        if (
            true === $platform instanceof AbstractMySQLPlatform
            && true === ($column['update'] ?? false)
        ) {
            return $sqlDeclaration . ' ON UPDATE CURRENT_TIMESTAMP';
        }

        return $sqlDeclaration;
    }
}
