<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Contract;

use PrecisionSoft\Doctrine\Type\Test\Utility\TestConcreteType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestPrefixedType;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;

/** @internal */
final class AbstractTypeTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(stdClass::class);
    }

    public function testGetDefaultNameReturnsShortClassName(): void
    {
        $defaultName = TestConcreteType::getDefaultName();

        static::assertSame('TestConcreteType', $defaultName);
    }

    public function testGetDefaultNamePrefixReturnsNull(): void
    {
        $defaultNamePrefix = TestConcreteType::getDefaultNamePrefix();

        static::assertNull($defaultNamePrefix);
    }

    public function testGetDefaultNameWithPrefixReturnsPrefixedShortClassName(): void
    {
        $defaultName = TestPrefixedType::getDefaultName();

        static::assertSame('myprefix_TestPrefixedType', $defaultName);
    }

    public function testGetDefaultNamePrefixReturnsPrefix(): void
    {
        $defaultNamePrefix = TestPrefixedType::getDefaultNamePrefix();

        static::assertSame('myprefix_', $defaultNamePrefix);
    }
}
