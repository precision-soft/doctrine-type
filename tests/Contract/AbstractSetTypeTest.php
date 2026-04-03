<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Contract;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PrecisionSoft\Doctrine\Type\Contract\AbstractSetType;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedSetType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleSetType;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;

class AbstractSetTypeTest extends AbstractTestCase
{
    private MySQLPlatform $mysqlPlatform;

    public static function getMockDto(): MockDto
    {
        return new MockDto(stdClass::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mysqlPlatform = new MySQLPlatform();
    }

    public function testConvertToDatabaseValueNull(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $databaseValue = $testBackedSetType->convertToDatabaseValue(null, $this->mysqlPlatform);

        self::assertNull($databaseValue);
    }

    public function testConvertToDatabaseValueEmptyArray(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $databaseValue = $testBackedSetType->convertToDatabaseValue([], $this->mysqlPlatform);

        self::assertNull($databaseValue);
    }

    public function testConvertToDatabaseValueSingleBackedEnum(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $databaseValue = $testBackedSetType->convertToDatabaseValue([TestBackedEnum::first], $this->mysqlPlatform);

        self::assertSame('first_value', $databaseValue);
    }

    public function testConvertToDatabaseValueMultipleBackedEnums(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $databaseValue = $testBackedSetType->convertToDatabaseValue(
            [TestBackedEnum::first, TestBackedEnum::third],
            $this->mysqlPlatform,
        );

        self::assertSame('first_value,third_value', $databaseValue);
    }

    public function testConvertToDatabaseValueSimpleEnums(): void
    {
        $testSimpleSetType = new TestSimpleSetType();
        $databaseValue = $testSimpleSetType->convertToDatabaseValue(
            [TestSimpleEnum::alpha, TestSimpleEnum::gamma],
            $this->mysqlPlatform,
        );

        self::assertSame('alpha,gamma', $databaseValue);
    }

    public function testConvertToPhpValueNull(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $phpValue = $testBackedSetType->convertToPHPValue(null, $this->mysqlPlatform);

        self::assertNull($phpValue);
    }

    public function testConvertToPhpValueEmptyString(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $phpValue = $testBackedSetType->convertToPHPValue('', $this->mysqlPlatform);

        self::assertNull($phpValue);
    }

    public function testConvertToPhpValueSingle(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $phpValue = $testBackedSetType->convertToPHPValue('first_value', $this->mysqlPlatform);

        self::assertSame([TestBackedEnum::first], $phpValue);
    }

    public function testConvertToPhpValueMultiple(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $phpValue = $testBackedSetType->convertToPHPValue('first_value,third_value', $this->mysqlPlatform);

        self::assertSame([TestBackedEnum::first, TestBackedEnum::third], $phpValue);
    }

    public function testGetSqlDeclarationMysql(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $sqlDeclaration = $testBackedSetType->getSQLDeclaration([], $this->mysqlPlatform);

        self::assertStringStartsWith('SET(', $sqlDeclaration);
        self::assertStringContainsString('first_value', $sqlDeclaration);
    }

    public function testGetSqlDeclarationNonMysql(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $sqlDeclaration = $testBackedSetType->getSQLDeclaration([], new PostgreSQLPlatform());

        self::assertStringNotContainsString('SET(', $sqlDeclaration);
    }

    public function testConvertToDatabaseValueWithCommaThrows(): void
    {
        $anonymousSetType = new class extends AbstractSetType {
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

        $anonymousSetType->convertToDatabaseValue(['has,comma'], $this->mysqlPlatform);
    }

    public function testInvalidBackedEnumValueThrows(): void
    {
        $testBackedSetType = new TestBackedSetType();

        $this->expectException(InvalidTypeValueException::class);

        $testBackedSetType->convertToPHPValue('nonexistent', $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueFiltersNullValues(): void
    {
        $anonymousSetType = new class extends AbstractSetType {
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

        $databaseValue = $anonymousSetType->convertToDatabaseValue([null, 'valid', null, 'other'], $this->mysqlPlatform);

        self::assertSame('valid,other', $databaseValue);
    }

    public function testConvertToDatabaseValueAllNullsReturnsNull(): void
    {
        $anonymousSetType = new class extends AbstractSetType {
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

        $databaseValue = $anonymousSetType->convertToDatabaseValue([null, null], $this->mysqlPlatform);

        self::assertNull($databaseValue);
    }

    public function testConvertToDatabaseValueDuplicateBackedEnumsAreDeduplicated(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $databaseValue = $testBackedSetType->convertToDatabaseValue(
            [TestBackedEnum::first, TestBackedEnum::first, TestBackedEnum::second],
            $this->mysqlPlatform,
        );

        self::assertSame('first_value,second_value', $databaseValue);
    }

    public function testConvertToDatabaseValueDeduplicatesSimpleEnums(): void
    {
        $testSimpleSetType = new TestSimpleSetType();
        $databaseValue = $testSimpleSetType->convertToDatabaseValue(
            [TestSimpleEnum::alpha, TestSimpleEnum::alpha, TestSimpleEnum::beta],
            $this->mysqlPlatform,
        );

        self::assertSame('alpha,beta', $databaseValue);
    }

    public function testConvertToDatabaseValueAllSameValuesDeduplicatesToSingle(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $databaseValue = $testBackedSetType->convertToDatabaseValue(
            [TestBackedEnum::first, TestBackedEnum::first],
            $this->mysqlPlatform,
        );

        self::assertSame('first_value', $databaseValue);
    }

    public function testConvertToPhpValueSimpleEnum(): void
    {
        $testSimpleSetType = new TestSimpleSetType();
        $phpValue = $testSimpleSetType->convertToPHPValue('alpha,gamma', $this->mysqlPlatform);

        self::assertSame([TestSimpleEnum::alpha, TestSimpleEnum::gamma], $phpValue);
    }

    public function testConvertToPhpValueSingleSimpleEnum(): void
    {
        $testSimpleSetType = new TestSimpleSetType();
        $phpValue = $testSimpleSetType->convertToPHPValue('beta', $this->mysqlPlatform);

        self::assertSame([TestSimpleEnum::beta], $phpValue);
    }

    public function testGetSqlDeclarationMysqlSimpleSet(): void
    {
        $testSimpleSetType = new TestSimpleSetType();
        $sqlDeclaration = $testSimpleSetType->getSQLDeclaration([], $this->mysqlPlatform);

        self::assertStringStartsWith('SET(', $sqlDeclaration);
        self::assertStringContainsString('alpha', $sqlDeclaration);
        self::assertStringContainsString('beta', $sqlDeclaration);
        self::assertStringContainsString('gamma', $sqlDeclaration);
    }

    public function testGetSqlDeclarationNonMysqlSimpleSet(): void
    {
        $testSimpleSetType = new TestSimpleSetType();
        $sqlDeclaration = $testSimpleSetType->getSQLDeclaration([], new PostgreSQLPlatform());

        self::assertStringNotContainsString('SET(', $sqlDeclaration);
    }

    public function testConvertToDatabaseValueMixedNullAndDuplicates(): void
    {
        $anonymousSetType = new class extends AbstractSetType {
            public function getValues(): array
            {
                return ['a', 'b'];
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

        $databaseValue = $anonymousSetType->convertToDatabaseValue([null, 'a', null, 'a', 'b'], $this->mysqlPlatform);

        self::assertSame('a,b', $databaseValue);
    }

    public function testConvertToDatabaseValueNonStringIntegerValues(): void
    {
        $anonymousSetType = new class extends AbstractSetType {
            public function getValues(): array
            {
                return [1, 2, 3];
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

        $databaseValue = $anonymousSetType->convertToDatabaseValue([1, 2, 3], $this->mysqlPlatform);

        self::assertSame('1,2,3', $databaseValue);
    }

    public function testInvalidSimpleEnumValueThrows(): void
    {
        $testSimpleSetType = new TestSimpleSetType();

        $this->expectException(InvalidTypeValueException::class);

        $testSimpleSetType->convertToPHPValue('nonexistent', $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueScalarEnumThrows(): void
    {
        $testBackedSetType = new TestBackedSetType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected array for set type');

        $testBackedSetType->convertToDatabaseValue(TestBackedEnum::first, $this->mysqlPlatform);
    }
}
