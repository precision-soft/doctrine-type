<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Contract;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedSetType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleSetType;

class AbstractSetTypeTest extends TestCase
{
    private MySQLPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = new MySQLPlatform();
    }

    public function testConvertToDatabaseValueNull(): void
    {
        $type = new TestBackedSetType();
        $result = $type->convertToDatabaseValue(null, $this->platform);

        self::assertNull($result);
    }

    public function testConvertToDatabaseValueEmptyArray(): void
    {
        $type = new TestBackedSetType();
        $result = $type->convertToDatabaseValue([], $this->platform);

        self::assertNull($result);
    }

    public function testConvertToDatabaseValueSingleBackedEnum(): void
    {
        $type = new TestBackedSetType();
        $result = $type->convertToDatabaseValue([TestBackedEnum::first], $this->platform);

        self::assertSame('first_value', $result);
    }

    public function testConvertToDatabaseValueMultipleBackedEnums(): void
    {
        $type = new TestBackedSetType();
        $result = $type->convertToDatabaseValue(
            [TestBackedEnum::first, TestBackedEnum::third],
            $this->platform,
        );

        self::assertSame('first_value,third_value', $result);
    }

    public function testConvertToDatabaseValueSimpleEnums(): void
    {
        $type = new TestSimpleSetType();
        $result = $type->convertToDatabaseValue(
            [TestSimpleEnum::alpha, TestSimpleEnum::gamma],
            $this->platform,
        );

        self::assertSame('alpha,gamma', $result);
    }

    public function testConvertToPhpValueNull(): void
    {
        $type = new TestBackedSetType();
        $result = $type->convertToPHPValue(null, $this->platform);

        self::assertNull($result);
    }

    public function testConvertToPhpValueEmptyString(): void
    {
        $type = new TestBackedSetType();
        $result = $type->convertToPHPValue('', $this->platform);

        self::assertNull($result);
    }

    public function testConvertToPhpValueSingle(): void
    {
        $type = new TestBackedSetType();
        $result = $type->convertToPHPValue('first_value', $this->platform);

        self::assertSame([TestBackedEnum::first], $result);
    }

    public function testConvertToPhpValueMultiple(): void
    {
        $type = new TestBackedSetType();
        $result = $type->convertToPHPValue('first_value,third_value', $this->platform);

        self::assertSame([TestBackedEnum::first, TestBackedEnum::third], $result);
    }

    public function testGetSqlDeclarationMysql(): void
    {
        $type = new TestBackedSetType();
        $result = $type->getSQLDeclaration([], $this->platform);

        self::assertStringStartsWith('SET(', $result);
        self::assertStringContainsString('first_value', $result);
    }

    public function testGetSqlDeclarationNonMysql(): void
    {
        $type = new TestBackedSetType();
        $result = $type->getSQLDeclaration([], new PostgreSQLPlatform());

        self::assertStringNotContainsString('SET(', $result);
    }

    public function testConvertToDatabaseValueWithCommaThrows(): void
    {
        $type = new class extends \PrecisionSoft\Doctrine\Type\Contract\AbstractSetType {
            public function getValues(): array
            {
                return ['valid', 'has,comma'];
            }

            public function convertValueToDatabase(mixed $value): mixed
            {
                return $value;
            }

            public function convertValueToPhp(mixed $value): mixed
            {
                return $value;
            }
        };

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('must not contain a comma');

        $type->convertToDatabaseValue(['has,comma'], $this->platform);
    }

    public function testInvalidBackedEnumValueThrows(): void
    {
        $type = new TestBackedSetType();

        $this->expectException(InvalidTypeValueException::class);

        $type->convertToPHPValue('nonexistent', $this->platform);
    }

    public function testConvertToDatabaseValueFiltersNullValues(): void
    {
        $type = new class extends \PrecisionSoft\Doctrine\Type\Contract\AbstractSetType {
            public function getValues(): array
            {
                return ['valid', 'other'];
            }

            public function convertValueToDatabase(mixed $value): mixed
            {
                return $value;
            }

            public function convertValueToPhp(mixed $value): mixed
            {
                return $value;
            }
        };

        $result = $type->convertToDatabaseValue([null, 'valid', null, 'other'], $this->platform);

        self::assertSame('valid,other', $result);
    }

    public function testConvertToDatabaseValueAllNullsReturnsNull(): void
    {
        $type = new class extends \PrecisionSoft\Doctrine\Type\Contract\AbstractSetType {
            public function getValues(): array
            {
                return ['valid'];
            }

            public function convertValueToDatabase(mixed $value): mixed
            {
                return $value;
            }

            public function convertValueToPhp(mixed $value): mixed
            {
                return $value;
            }
        };

        $result = $type->convertToDatabaseValue([null, null], $this->platform);

        self::assertNull($result);
    }

    public function testConvertToDatabaseValueDuplicateBackedEnums(): void
    {
        $type = new TestBackedSetType();
        $result = $type->convertToDatabaseValue(
            [TestBackedEnum::first, TestBackedEnum::first, TestBackedEnum::second],
            $this->platform,
        );

        self::assertSame('first_value,first_value,second_value', $result);
    }
}
