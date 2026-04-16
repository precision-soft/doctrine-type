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

/** @internal */
final class EnumTypeTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(stdClass::class);
    }

    public function testEnumCasesExist(): void
    {
        $enumTypeCases = EnumType::cases();

        static::assertCount(3, $enumTypeCases);
    }

    public function testNotEnumCase(): void
    {
        static::assertSame('notEnum', EnumType::notEnum->name);
    }

    public function testSimpleCase(): void
    {
        static::assertSame('simple', EnumType::simple->name);
    }

    public function testBackedCase(): void
    {
        static::assertSame('backed', EnumType::backed->name);
    }
}
