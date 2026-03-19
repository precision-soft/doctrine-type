<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Contract;

use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnumType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnumType;

class AbstractPhpEnumTypeTest extends TestCase
{
    public function testBackedEnumConvertValueToDatabase(): void
    {
        $type = new TestBackedEnumType();
        $result = $type->convertValueToDatabase(TestBackedEnum::second);

        self::assertSame('second_value', $result);
    }

    public function testBackedEnumConvertValueToDatabaseInvalidThrows(): void
    {
        $type = new TestBackedEnumType();

        $this->expectException(InvalidTypeValueException::class);

        $type->convertValueToDatabase('not_an_enum');
    }

    public function testSimpleEnumConvertValueToDatabase(): void
    {
        $type = new TestSimpleEnumType();
        $result = $type->convertValueToDatabase(TestSimpleEnum::beta);

        self::assertSame('beta', $result);
    }

    public function testSimpleEnumConvertValueToDatabaseInvalidThrows(): void
    {
        $type = new TestSimpleEnumType();

        $this->expectException(InvalidTypeValueException::class);

        $type->convertValueToDatabase('not_an_enum');
    }

    public function testBackedEnumConvertValueToPhp(): void
    {
        $type = new TestBackedEnumType();
        $result = $type->convertValueToPhp('second_value');

        self::assertSame(TestBackedEnum::second, $result);
    }

    public function testBackedEnumConvertValueToPhpInvalidThrows(): void
    {
        $type = new TestBackedEnumType();

        $this->expectException(InvalidTypeValueException::class);

        $type->convertValueToPhp('nonexistent');
    }

    public function testSimpleEnumConvertValueToPhp(): void
    {
        $type = new TestSimpleEnumType();
        $result = $type->convertValueToPhp('beta');

        self::assertSame(TestSimpleEnum::beta, $result);
    }

    public function testSimpleEnumConvertValueToPhpInvalidThrows(): void
    {
        $type = new TestSimpleEnumType();

        $this->expectException(InvalidTypeValueException::class);

        $type->convertValueToPhp('nonexistent');
    }

    public function testGetEnumValues(): void
    {
        $type = new TestBackedEnumType();
        $values = $type->getValues();

        self::assertCount(3, $values);
    }

    public function testNoEnumClassGetValuesThrows(): void
    {
        $type = new class extends \PrecisionSoft\Doctrine\Type\Contract\AbstractEnumType {};

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('you must use the enum class');

        $type->getValues();
    }

    public function testInvalidEnumClassThrows(): void
    {
        $type = new class extends \PrecisionSoft\Doctrine\Type\Contract\AbstractEnumType {
            public function getEnumClass(): string
            {
                return 'NonExistentClass';
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not exist');

        $type->getValues();
    }
}
