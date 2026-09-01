<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use PrecisionSoft\Doctrine\Type\Contract\AbstractTinyintType;

class UnsignedTinyintType extends AbstractTinyintType
{
    public const TINYINT = 'tinyint_unsigned';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['unsigned'] = true;

        return parent::getSQLDeclaration($column, $platform);
    }

    protected function isWithinRange(int $tinyintValue): bool
    {
        return $tinyintValue >= 0 && $tinyintValue <= 255;
    }

    protected function getRangeDescription(): string
    {
        return '0 to 255';
    }
}
