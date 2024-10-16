<?php

declare(strict_types=1);

namespace PrecisionSoft\Doctrine\Type\Enum;

enum EnumType
{
    case notEnum;
    case simple;
    case backed;
}
