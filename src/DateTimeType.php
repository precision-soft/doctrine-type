<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;

class DateTimeType extends \Doctrine\DBAL\Types\DateTimeType
{
    public function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform,
    ): string {
        $sqlDeclaration = parent::getSQLDeclaration($column, $platform);

        if (($platform instanceof MySqlPlatform) === true && false === empty($column['update'])) {
            return $sqlDeclaration . ' ON UPDATE CURRENT_TIMESTAMP';
        }

        return $sqlDeclaration;
    }
}
