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

    /** @info intentional non-case class constant used by AbstractPhpEnumTypeTest to verify `getEnumByName()` rejects non-case constants */
    public const NOT_A_CASE = 'not_a_case_value';
}
