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

        /** @info `column['update']` is a boolean flag on the column mapping enabling `ON UPDATE CURRENT_TIMESTAMP`; we accept any truthy value to tolerate annotations like `options={"update": 1}`, but schema designers should always pass a real boolean */
        if (
            true === $platform instanceof AbstractMySQLPlatform
            && true === ($column['update'] ?? false)
        ) {
            return $sqlDeclaration . ' ON UPDATE CURRENT_TIMESTAMP';
        }

        return $sqlDeclaration;
    }
}
