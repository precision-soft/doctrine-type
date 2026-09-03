<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Type;

use PrecisionSoft\Doctrine\Type\Contract\AbstractPortableEnumType;
use PrecisionSoft\Doctrine\Type\Example\Enum\ProductStatus;

/** the status is enforced by the server on every engine: a native ENUM on MySQL, a VARCHAR with a CHECK elsewhere */
class ProductStatusType extends AbstractPortableEnumType
{
    public function getEnumClass(): string
    {
        return ProductStatus::class;
    }
}
