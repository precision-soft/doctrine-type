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

        /** @info `column['update']` is a boolean flag on the column mapping enabling `ON UPDATE CURRENT_TIMESTAMP`; only a strict boolean `true` enables it, so schema designers must pass a real boolean (`options={"update": true}`) — a truthy `1` is intentionally not accepted */
        if (
            true === $platform instanceof AbstractMySQLPlatform
            && true === ($column['update'] ?? false)
        ) {
            return $sqlDeclaration . ' ON UPDATE CURRENT_TIMESTAMP';
        }

        return $sqlDeclaration;
    }
}
