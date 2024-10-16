<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Enum;

enum EnumType
{
    case notEnum;
    case simple;
    case backed;
}
