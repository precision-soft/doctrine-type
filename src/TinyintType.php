<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type;

use PrecisionSoft\Doctrine\Type\Contract\AbstractTinyintType;

/**
 * @deprecated use SignedTinyintType or UnsignedTinyintType so conversion can enforce the declared range
 */
class TinyintType extends AbstractTinyintType
{
    protected function isWithinRange(int $tinyintValue): bool
    {
        /* the combined signed+unsigned range: no column metadata reaches this call, so both halves are accepted */
        return $tinyintValue >= -128 && $tinyintValue <= 255;
    }

    protected function getRangeDescription(): string
    {
        return '-128 to 255';
    }
}
