<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Enum;

enum ProductStatus: string
{
    case draft = 'draft';
    case active = 'active';
    case discontinued = 'discontinued';
}
