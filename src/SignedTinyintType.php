<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use PrecisionSoft\Doctrine\Type\Contract\AbstractTinyintType;

class SignedTinyintType extends AbstractTinyintType
{
    public const TINYINT = 'tinyint_signed';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['unsigned'] = false;

        return parent::getSQLDeclaration($column, $platform);
    }

    protected function isWithinRange(int $tinyintValue): bool
    {
        return $tinyintValue >= -128 && $tinyintValue <= 127;
    }

    protected function getRangeDescription(): string
    {
        return '-128 to 127';
    }
}
