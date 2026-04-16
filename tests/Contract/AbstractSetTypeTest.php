<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Contract;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PrecisionSoft\Doctrine\Type\Contract\AbstractPhpEnumType;
use PrecisionSoft\Doctrine\Type\Contract\AbstractSetType;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedSetType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestIntBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestIntBackedSetType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleSetType;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;

/** @internal */
final class AbstractSetTypeTest extends AbstractTestCase
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

    protected function tearDown(): void
    {
        AbstractPhpEnumType::clearCache();

        parent::tearDown();
    }

    public function testConvertToDatabaseValueNull(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $databaseValue = $testBackedSetType->convertToDatabaseValue(null, $this->mysqlPlatform);

        static::assertNull($databaseValue);
    }

    public function testConvertToDatabaseValueEmptyArray(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $databaseValue = $testBackedSetType->convertToDatabaseValue([], $this->mysqlPlatform);

        static::assertNull($databaseValue);
    }

    public function testConvertToDatabaseValueSingleBackedEnum(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $databaseValue = $testBackedSetType->convertToDatabaseValue([TestBackedEnum::first], $this->mysqlPlatform);

        static::assertSame('first_value', $databaseValue);
    }

    public function testConvertToDatabaseValueMultipleBackedEnums(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $databaseValue = $testBackedSetType->convertToDatabaseValue(
            [TestBackedEnum::first, TestBackedEnum::third],
            $this->mysqlPlatform,
        );

        static::assertSame('first_value,third_value', $databaseValue);
    }

    public function testConvertToDatabaseValueSimpleEnums(): void
    {
        $testSimpleSetType = new TestSimpleSetType();
        $databaseValue = $testSimpleSetType->convertToDatabaseValue(
            [TestSimpleEnum::alpha, TestSimpleEnum::gamma],
            $this->mysqlPlatform,
        );

        static::assertSame('alpha,gamma', $databaseValue);
    }

    public function testConvertToPhpValueNull(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $phpValue = $testBackedSetType->convertToPHPValue(null, $this->mysqlPlatform);

        static::assertNull($phpValue);
    }

    public function testConvertToPhpValueEmptyString(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $phpValue = $testBackedSetType->convertToPHPValue('', $this->mysqlPlatform);

        static::assertNull($phpValue);
    }

    public function testConvertToPhpValueSingle(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $phpValue = $testBackedSetType->convertToPHPValue('first_value', $this->mysqlPlatform);

        static::assertSame([TestBackedEnum::first], $phpValue);
    }

    public function testConvertToPhpValueMultiple(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $phpValue = $testBackedSetType->convertToPHPValue('first_value,third_value', $this->mysqlPlatform);

        static::assertSame([TestBackedEnum::first, TestBackedEnum::third], $phpValue);
    }

    public function testGetSqlDeclarationMysql(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $sqlDeclaration = $testBackedSetType->getSQLDeclaration([], $this->mysqlPlatform);

        static::assertStringStartsWith('SET(', $sqlDeclaration);
        static::assertStringContainsString('first_value', $sqlDeclaration);
    }

    public function testGetSqlDeclarationNonMysql(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $sqlDeclaration = $testBackedSetType->getSQLDeclaration([], new PostgreSQLPlatform());

        static::assertStringNotContainsString('SET(', $sqlDeclaration);
        static::assertStringContainsString('VARCHAR(255)', $sqlDeclaration);
    }

    public function testConvertToDatabaseValueWithCommaThrows(): void
    {
        $anonymousSetType = new class extends AbstractSetType {
            /** @return array<int, mixed> */
            public function getValues(): array
            {
                return ['valid', 'has,comma'];
            }

            protected function convertValueToDatabase(mixed $value): mixed
            {
                return $value;
            }

            protected function convertValueToPhp(mixed $value): mixed
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
            /** @return array<int, mixed> */
            public function getValues(): array
            {
                return ['valid', 'other'];
            }

            protected function convertValueToDatabase(mixed $value): mixed
            {
                return $value;
            }

            protected function convertValueToPhp(mixed $value): mixed
            {
                return $value;
            }
        };

        $databaseValue = $anonymousSetType->convertToDatabaseValue([null, 'valid', null, 'other'], $this->mysqlPlatform);

        static::assertSame('valid,other', $databaseValue);
    }

    public function testConvertToDatabaseValueAllNullsReturnsNull(): void
    {
        $anonymousSetType = new class extends AbstractSetType {
            /** @return array<int, mixed> */
            public function getValues(): array
            {
                return ['valid'];
            }

            protected function convertValueToDatabase(mixed $value): mixed
            {
                return $value;
            }

            protected function convertValueToPhp(mixed $value): mixed
            {
                return $value;
            }
        };

        $databaseValue = $anonymousSetType->convertToDatabaseValue([null, null], $this->mysqlPlatform);

        static::assertNull($databaseValue);
    }

    public function testConvertToDatabaseValueDuplicateBackedEnumsAreDeduplicated(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $databaseValue = $testBackedSetType->convertToDatabaseValue(
            [TestBackedEnum::first, TestBackedEnum::first, TestBackedEnum::second],
            $this->mysqlPlatform,
        );

        static::assertSame('first_value,second_value', $databaseValue);
    }

    public function testConvertToDatabaseValueDeduplicatesSimpleEnums(): void
    {
        $testSimpleSetType = new TestSimpleSetType();
        $databaseValue = $testSimpleSetType->convertToDatabaseValue(
            [TestSimpleEnum::alpha, TestSimpleEnum::alpha, TestSimpleEnum::beta],
            $this->mysqlPlatform,
        );

        static::assertSame('alpha,beta', $databaseValue);
    }

    public function testConvertToDatabaseValueAllSameValuesDeduplicatesToSingle(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $databaseValue = $testBackedSetType->convertToDatabaseValue(
            [TestBackedEnum::first, TestBackedEnum::first],
            $this->mysqlPlatform,
        );

        static::assertSame('first_value', $databaseValue);
    }

    public function testConvertToPhpValueSimpleEnum(): void
    {
        $testSimpleSetType = new TestSimpleSetType();
        $phpValue = $testSimpleSetType->convertToPHPValue('alpha,gamma', $this->mysqlPlatform);

        static::assertSame([TestSimpleEnum::alpha, TestSimpleEnum::gamma], $phpValue);
    }

    public function testConvertToPhpValueSingleSimpleEnum(): void
    {
        $testSimpleSetType = new TestSimpleSetType();
        $phpValue = $testSimpleSetType->convertToPHPValue('beta', $this->mysqlPlatform);

        static::assertSame([TestSimpleEnum::beta], $phpValue);
    }

    public function testGetSqlDeclarationMysqlSimpleSet(): void
    {
        $testSimpleSetType = new TestSimpleSetType();
        $sqlDeclaration = $testSimpleSetType->getSQLDeclaration([], $this->mysqlPlatform);

        static::assertStringStartsWith('SET(', $sqlDeclaration);
        static::assertStringContainsString('alpha', $sqlDeclaration);
        static::assertStringContainsString('beta', $sqlDeclaration);
        static::assertStringContainsString('gamma', $sqlDeclaration);
    }

    public function testGetSqlDeclarationNonMysqlSimpleSet(): void
    {
        $testSimpleSetType = new TestSimpleSetType();
        $sqlDeclaration = $testSimpleSetType->getSQLDeclaration([], new PostgreSQLPlatform());

        static::assertStringNotContainsString('SET(', $sqlDeclaration);
        static::assertStringContainsString('VARCHAR(255)', $sqlDeclaration);
    }

    public function testConvertToDatabaseValueMixedNullAndDuplicates(): void
    {
        $anonymousSetType = new class extends AbstractSetType {
            /** @return array<int, mixed> */
            public function getValues(): array
            {
                return ['a', 'b'];
            }

            protected function convertValueToDatabase(mixed $value): mixed
            {
                return $value;
            }

            protected function convertValueToPhp(mixed $value): mixed
            {
                return $value;
            }
        };

        $databaseValue = $anonymousSetType->convertToDatabaseValue([null, 'a', null, 'a', 'b'], $this->mysqlPlatform);

        static::assertSame('a,b', $databaseValue);
    }

    public function testConvertToPhpValueNonStringThrows(): void
    {
        $testBackedSetType = new TestBackedSetType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected string for set type');

        $testBackedSetType->convertToPHPValue(123, $this->mysqlPlatform);
    }

    public function testConvertToPhpValuePassesAlreadyHydratedArrayThrough(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $alreadyHydrated = [TestBackedEnum::first, TestBackedEnum::third];

        $phpValue = $testBackedSetType->convertToPHPValue($alreadyHydrated, $this->mysqlPlatform);

        static::assertSame($alreadyHydrated, $phpValue);
    }

    public function testConvertToPhpValueObjectThrows(): void
    {
        $testBackedSetType = new TestBackedSetType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected string for set type');

        $testBackedSetType->convertToPHPValue(new \stdClass(), $this->mysqlPlatform);
    }

    public function testConvertToPhpValueWhitespacePadded(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $phpValue = $testBackedSetType->convertToPHPValue(' first_value , third_value ', $this->mysqlPlatform);

        static::assertSame([TestBackedEnum::first, TestBackedEnum::third], $phpValue);
    }

    public function testConvertToDatabaseValueNonStringIntegerValues(): void
    {
        $anonymousSetType = new class extends AbstractSetType {
            /** @return array<int, mixed> */
            public function getValues(): array
            {
                return [1, 2, 3];
            }

            protected function convertValueToDatabase(mixed $value): mixed
            {
                return $value;
            }

            protected function convertValueToPhp(mixed $value): mixed
            {
                return $value;
            }
        };

        $databaseValue = $anonymousSetType->convertToDatabaseValue([1, 2, 3], $this->mysqlPlatform);

        static::assertSame('1,2,3', $databaseValue);
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

    public function testConvertToDatabaseValueNonEnumWhenEnumClassSetThrows(): void
    {
        $testBackedSetType = new TestBackedSetType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected enum case of');

        $testBackedSetType->convertToDatabaseValue(['not-an-enum'], $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueNonEnumIntegerWhenEnumClassSetThrows(): void
    {
        $testSimpleSetType = new TestSimpleSetType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected enum case of');

        $testSimpleSetType->convertToDatabaseValue([42], $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueWrongEnumClassThrows(): void
    {
        $testBackedSetType = new TestBackedSetType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('does not belong to');

        $testBackedSetType->convertToDatabaseValue([TestSimpleEnum::alpha], $this->mysqlPlatform);
    }

    public function testGetSqlDeclarationMariaDb(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $sqlDeclaration = $testBackedSetType->getSQLDeclaration([], new MariaDBPlatform());

        static::assertStringStartsWith('SET(', $sqlDeclaration);
        static::assertStringContainsString('first_value', $sqlDeclaration);
    }

    public function testIntBackedEnumSetConvertRoundTrip(): void
    {
        $testIntBackedSetType = new TestIntBackedSetType();

        $databaseValue = $testIntBackedSetType->convertToDatabaseValue(
            [TestIntBackedEnum::low, TestIntBackedEnum::high],
            $this->mysqlPlatform,
        );
        $phpValue = $testIntBackedSetType->convertToPHPValue($databaseValue, $this->mysqlPlatform);

        static::assertSame('1,10', $databaseValue);
        static::assertSame([TestIntBackedEnum::low, TestIntBackedEnum::high], $phpValue);
    }

    public function testIntBackedEnumSetGetSqlDeclarationMysqlQuotesNumericValues(): void
    {
        $testIntBackedSetType = new TestIntBackedSetType();
        $sqlDeclaration = $testIntBackedSetType->getSQLDeclaration([], $this->mysqlPlatform);

        static::assertStringStartsWith('SET(', $sqlDeclaration);
        static::assertStringContainsString("'1'", $sqlDeclaration);
        static::assertStringContainsString("'5'", $sqlDeclaration);
        static::assertStringContainsString("'10'", $sqlDeclaration);
    }

    public function testGetSqlDeclarationSqlite(): void
    {
        $testBackedSetType = new TestBackedSetType();
        $sqlDeclaration = $testBackedSetType->getSQLDeclaration([], new SQLitePlatform());

        static::assertStringNotContainsString('SET(', $sqlDeclaration);
        static::assertStringContainsString('VARCHAR(255)', $sqlDeclaration);
    }

    public function testConvertToDatabaseValueNullElementInTypedEnumSetThrows(): void
    {
        $testBackedSetType = new TestBackedSetType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('does not allow null elements for typed enum sets');

        $testBackedSetType->convertToDatabaseValue([TestBackedEnum::first, null, TestBackedEnum::third], $this->mysqlPlatform);
    }
}
