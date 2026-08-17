<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Utility;

enum TestSimpleEnum
{
    case alpha;
    case beta;
    case gamma;

    public const NOT_A_CASE = 'not_a_case_value';

    /** a case under another name, which `getEnumByName()` must still reject */
    public const ALIAS = self::alpha;
}
