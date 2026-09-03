<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Enum;

enum SalesChannel
{
    case shop;
    case marketplace;
    case wholesale;
}
