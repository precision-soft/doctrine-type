<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Enum;

enum EnumType
{
    /** @info `notEnum` marks a Type that does not map a PHP enum class — the value passes through `convertValueToDatabase`/`convertValueToPhp` unchanged. `simple`/`backed` distinguish PHP UnitEnum vs BackedEnum, which drives whether lookup uses the case name (`getEnumByName`) or the backing value (`getEnumByValue`). The three cases are intentionally asymmetric: `notEnum` is a pass-through, while `simple`/`backed` trigger enum resolution and validation */
    case notEnum;
    case simple;
    case backed;
}
