<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Utility;

enum TestBackedEnum: string
{
    case first = 'first_value';
    case second = 'second_value';
    case third = 'third_value';
}
