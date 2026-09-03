<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Enum;

enum Currency: string
{
    case EUR = 'EUR';
    case RON = 'RON';
    case USD = 'USD';
}
