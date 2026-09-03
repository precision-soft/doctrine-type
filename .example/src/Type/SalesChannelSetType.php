<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Type;

use PrecisionSoft\Doctrine\Type\Contract\AbstractSetType;
use PrecisionSoft\Doctrine\Type\Example\Enum\SalesChannel;

/** a pure enum stored as a MySQL SET of case names, a comma-separated VARCHAR elsewhere */
class SalesChannelSetType extends AbstractSetType
{
    public function getEnumClass(): string
    {
        return SalesChannel::class;
    }
}
