<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Enum;

use PrecisionSoft\Doctrine\Type\Enum\EnumType;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;

class EnumTypeTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(stdClass::class);
    }

    public function testEnumCasesExist(): void
    {
        $enumTypeCases = EnumType::cases();

        self::assertCount(3, $enumTypeCases);
    }

    public function testNotEnumCase(): void
    {
        self::assertSame('notEnum', EnumType::notEnum->name);
    }

    public function testSimpleCase(): void
    {
        self::assertSame('simple', EnumType::simple->name);
    }

    public function testBackedCase(): void
    {
        self::assertSame('backed', EnumType::backed->name);
    }
}
