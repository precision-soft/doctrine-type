<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Contract;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestConcreteType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestPrefixedType;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;

class AbstractTypeTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(stdClass::class);
    }

    public function testGetDefaultNameReturnsShortClassName(): void
    {
        $defaultName = TestConcreteType::getDefaultName();

        self::assertSame('TestConcreteType', $defaultName);
    }

    public function testGetDefaultNamePrefixReturnsNull(): void
    {
        $defaultNamePrefix = TestConcreteType::getDefaultNamePrefix();

        self::assertNull($defaultNamePrefix);
    }

    public function testGetDefaultNameWithPrefixReturnsPrefixedShortClassName(): void
    {
        $defaultName = TestPrefixedType::getDefaultName();

        self::assertSame('myprefix_TestPrefixedType', $defaultName);
    }

    public function testGetDefaultNamePrefixReturnsPrefix(): void
    {
        $defaultNamePrefix = TestPrefixedType::getDefaultNamePrefix();

        self::assertSame('myprefix_', $defaultNamePrefix);
    }

    public function testRequiresSqlCommentHintReturnsTrue(): void
    {
        $testConcreteType = new TestConcreteType();

        self::assertSame(true, $testConcreteType->requiresSQLCommentHint(new MySQLPlatform()));
    }
}
