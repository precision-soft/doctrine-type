<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Type;

use PrecisionSoft\Doctrine\Type\Contract\AbstractEnumType;
use PrecisionSoft\Doctrine\Type\Example\Enum\Currency;

/** a native ENUM on MySQL, a plain VARCHAR everywhere else: the type validates, the server does not */
class CurrencyType extends AbstractEnumType
{
    public function getEnumClass(): string
    {
        return Currency::class;
    }
}
