<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Types\DateTimeType as DoctrineDateTimeType;

class DateTimeType extends DoctrineDateTimeType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $sqlDeclaration = parent::getSQLDeclaration($column, $platform);

        if (
            true === $platform instanceof MySQLPlatform
            && true === isset($column['update']) && '' !== $column['update'] && null !== $column['update']
        ) {
            return $sqlDeclaration . ' ON UPDATE CURRENT_TIMESTAMP';
        }

        return $sqlDeclaration;
    }
}
