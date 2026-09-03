<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Contract;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PrecisionSoft\Doctrine\Type\Contract\AbstractEnumType;
use PrecisionSoft\Doctrine\Type\Contract\AbstractPhpEnumType;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnumType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestIntBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestIntBackedEnumType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestNegativeIntBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestNegativeIntBackedEnumType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnumType;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;
use UnitEnum;

/** @internal */
final class AbstractPhpEnumTypeTest extends AbstractTestCase
{
    private MySQLPlatform $mysqlPlatform;

    public static function getMockDto(): MockDto
    {
        return new MockDto(stdClass::class);
    }

    /** @return array<string, array{string}> */
    public static function provideNonIntegerFormattedStrings(): array
    {
        return [
            'trailing characters after a valid case value' => ['5abc'],
            'leading characters before a valid case value' => ['abc5'],
            'trailing characters after a negative value' => ['-5abc'],
            'inner whitespace' => ['5 5'],
        ];
    }

    public function testBackedEnumConvertToDatabaseValue(): void
    {
        $testBackedEnumType = new TestBackedEnumType();
        $databaseValue = $testBackedEnumType->convertToDatabaseValue(TestBackedEnum::second, $this->mysqlPlatform);

        static::assertSame('second_value', $databaseValue);
    }

    public function testBackedEnumConvertToDatabaseValueInvalidThrows(): void
    {
        $testBackedEnumType = new TestBackedEnumType();

        $this->expectException(InvalidTypeValueException::class);

        $testBackedEnumType->convertToDatabaseValue('not_an_enum', $this->mysqlPlatform);
    }

    public function testSimpleEnumConvertToDatabaseValue(): void
    {
        $testSimpleEnumType = new TestSimpleEnumType();
        $databaseValue = $testSimpleEnumType->convertToDatabaseValue(TestSimpleEnum::beta, $this->mysqlPlatform);

        static::assertSame('beta', $databaseValue);
    }

    public function testSimpleEnumConvertToDatabaseValueInvalidThrows(): void
    {
        $testSimpleEnumType = new TestSimpleEnumType();

        $this->expectException(InvalidTypeValueException::class);

        $testSimpleEnumType->convertToDatabaseValue('not_an_enum', $this->mysqlPlatform);
    }

    public function testBackedEnumConvertToPhpValue(): void
    {
        $testBackedEnumType = new TestBackedEnumType();
        $phpValue = $testBackedEnumType->convertToPHPValue('second_value', $this->mysqlPlatform);

        static::assertSame(TestBackedEnum::second, $phpValue);
    }

    public function testBackedEnumConvertToPhpValueInvalidThrows(): void
    {
        $testBackedEnumType = new TestBackedEnumType();

        $this->expectException(InvalidTypeValueException::class);

        $testBackedEnumType->convertToPHPValue('nonexistent', $this->mysqlPlatform);
    }

    public function testSimpleEnumConvertToPhpValue(): void
    {
        $testSimpleEnumType = new TestSimpleEnumType();
        $phpValue = $testSimpleEnumType->convertToPHPValue('beta', $this->mysqlPlatform);

        static::assertSame(TestSimpleEnum::beta, $phpValue);
    }

    public function testSimpleEnumConvertToPhpValueInvalidThrows(): void
    {
        $testSimpleEnumType = new TestSimpleEnumType();

        $this->expectException(InvalidTypeValueException::class);

        $testSimpleEnumType->convertToPHPValue('nonexistent', $this->mysqlPlatform);
    }

    public function testSimpleEnumConvertToPhpValueClassConstantThrows(): void
    {
        $testSimpleEnumType = new TestSimpleEnumType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('invalid enum value `NOT_A_CASE`');

        $testSimpleEnumType->convertToPHPValue('NOT_A_CASE', $this->mysqlPlatform);
    }

    public function testSimpleEnumConvertToPhpValueNonStringThrows(): void
    {
        $testSimpleEnumType = new TestSimpleEnumType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected string enum case name');

        $testSimpleEnumType->convertToPHPValue(42, $this->mysqlPlatform);
    }

    public function testGetEnumValues(): void
    {
        $testBackedEnumType = new TestBackedEnumType();
        $enumValues = $testBackedEnumType->getValues();

        static::assertCount(3, $enumValues);
    }

    public function testNoEnumClassGetValuesThrows(): void
    {
        $anonymousEnumType = new class extends AbstractEnumType {};

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('you must use the enum class');

        $anonymousEnumType->getValues();
    }

    public function testInvalidEnumClassThrows(): void
    {
        $anonymousEnumType = new class extends AbstractEnumType {
            public function getEnumClass(): string
            {
                /** @phpstan-ignore return.type */
                return 'NonExistentClass';
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not exist');

        $anonymousEnumType->getValues();
    }

    public function testNonEnumExistingClassThrows(): void
    {
        $anonymousEnumType = new class extends AbstractEnumType {
            public function getEnumClass(): string
            {
                /** @phpstan-ignore return.type */
                return stdClass::class;
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not exist');

        $anonymousEnumType->getValues();
    }

    public function testClearCacheResetsEnumTypeCache(): void
    {
        $testBackedEnumType = new TestBackedEnumType();

        $testBackedEnumType->getValues();

        AbstractPhpEnumType::clearCache();

        $enumValues = $testBackedEnumType->getValues();

        static::assertCount(3, $enumValues);
    }

    public function testNotEnumConvertToDatabaseValuePassesThrough(): void
    {
        $anonymousEnumType = new class extends AbstractEnumType {};

        $databaseValue = $anonymousEnumType->convertToDatabaseValue('raw_value', $this->mysqlPlatform);

        static::assertSame('raw_value', $databaseValue);
    }

    public function testNotEnumConvertToPhpValuePassesThrough(): void
    {
        $anonymousEnumType = new class extends AbstractEnumType {};

        $phpValue = $anonymousEnumType->convertToPHPValue('raw_value', $this->mysqlPlatform);

        static::assertSame('raw_value', $phpValue);
    }

    public function testGetEnumValuesWithNotEnumThrows(): void
    {
        $anonymousEnumType = new class extends AbstractEnumType {
            /** @return array<int, UnitEnum> */
            public function callGetEnumValues(): array
            {
                return $this->getEnumValues();
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invalid enum class');

        $anonymousEnumType->callGetEnumValues();
    }

    public function testEnumTypeCacheIsUsedOnSubsequentCalls(): void
    {
        $testBackedEnumType = new TestBackedEnumType();

        $firstDatabaseValue = $testBackedEnumType->convertToDatabaseValue(TestBackedEnum::first, $this->mysqlPlatform);

        $secondDatabaseValue = $testBackedEnumType->convertToDatabaseValue(TestBackedEnum::second, $this->mysqlPlatform);

        static::assertSame('first_value', $firstDatabaseValue);
        static::assertSame('second_value', $secondDatabaseValue);
    }

    public function testSimpleEnumGetEnumValues(): void
    {
        $testSimpleEnumType = new TestSimpleEnumType();
        $enumValues = $testSimpleEnumType->getValues();

        static::assertCount(3, $enumValues);
        static::assertSame(TestSimpleEnum::alpha, $enumValues[0]);
        static::assertSame(TestSimpleEnum::beta, $enumValues[1]);
        static::assertSame(TestSimpleEnum::gamma, $enumValues[2]);
    }

    public function testGetEnumClassReturnsNullByDefault(): void
    {
        $anonymousEnumType = new class extends AbstractEnumType {};

        static::assertNull($anonymousEnumType->getEnumClass());
    }

    public function testBackedEnumGetEnumClassReturnsClass(): void
    {
        $testBackedEnumType = new TestBackedEnumType();

        static::assertSame(TestBackedEnum::class, $testBackedEnumType->getEnumClass());
    }

    public function testIntBackedEnumConvertToDatabaseValue(): void
    {
        $testIntBackedEnumType = new TestIntBackedEnumType();
        $databaseValue = $testIntBackedEnumType->convertToDatabaseValue(TestIntBackedEnum::medium, $this->mysqlPlatform);

        static::assertSame(5, $databaseValue);
    }

    public function testIntBackedEnumConvertToPhpValue(): void
    {
        $testIntBackedEnumType = new TestIntBackedEnumType();
        $phpValue = $testIntBackedEnumType->convertToPHPValue('5', $this->mysqlPlatform);

        static::assertSame(TestIntBackedEnum::medium, $phpValue);
    }

    public function testIntBackedEnumConvertToPhpValueInvalidThrows(): void
    {
        $testIntBackedEnumType = new TestIntBackedEnumType();

        $this->expectException(InvalidTypeValueException::class);

        $testIntBackedEnumType->convertToPHPValue('999', $this->mysqlPlatform);
    }

    public function testBackedEnumConvertToPhpValuePassesAlreadyHydratedEnumThrough(): void
    {
        $testBackedEnumType = new TestBackedEnumType();
        $phpValue = $testBackedEnumType->convertToPHPValue(TestBackedEnum::second, $this->mysqlPlatform);

        static::assertSame(TestBackedEnum::second, $phpValue);
    }

    public function testSimpleEnumConvertToPhpValuePassesAlreadyHydratedEnumThrough(): void
    {
        $testSimpleEnumType = new TestSimpleEnumType();
        $phpValue = $testSimpleEnumType->convertToPHPValue(TestSimpleEnum::beta, $this->mysqlPlatform);

        static::assertSame(TestSimpleEnum::beta, $phpValue);
    }

    public function testIntBackedEnumConvertToPhpValueNonIntStringThrows(): void
    {
        $testIntBackedEnumType = new TestIntBackedEnumType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected int or integer-formatted string');

        $testIntBackedEnumType->convertToPHPValue('not_a_number', $this->mysqlPlatform);
    }

    #[DataProvider('provideNonIntegerFormattedStrings')]
    public function testIntBackedEnumConvertToPhpValueRejectsDigitsMixedWithOtherCharacters(string $databaseValue): void
    {
        $testIntBackedEnumType = new TestIntBackedEnumType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected int or integer-formatted string');

        $testIntBackedEnumType->convertToPHPValue($databaseValue, $this->mysqlPlatform);
    }

    public function testStringBackedEnumConvertToPhpValueIntThrows(): void
    {
        $testBackedEnumType = new TestBackedEnumType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected string for type');

        $testBackedEnumType->convertToPHPValue(42, $this->mysqlPlatform);
    }

    public function testBuildSqlDeclarationCacheReturnsIdenticalResult(): void
    {
        $testBackedEnumType = new TestBackedEnumType();

        $firstCall = $testBackedEnumType->getSQLDeclaration([], $this->mysqlPlatform);
        $secondCall = $testBackedEnumType->getSQLDeclaration([], $this->mysqlPlatform);

        static::assertSame($firstCall, $secondCall);
    }

    public function testBuildSqlDeclarationCacheDistinguishesColumnArguments(): void
    {
        $anonymousEnumType = new class extends AbstractEnumType {
            public function getEnumClass(): string
            {
                return TestBackedEnum::class;
            }
        };

        $postgresPlatform = new PostgreSQLPlatform();

        $shortLength = $anonymousEnumType->getSQLDeclaration(['length' => 64], $postgresPlatform);
        $longLength = $anonymousEnumType->getSQLDeclaration(['length' => 255], $postgresPlatform);

        static::assertNotSame($shortLength, $longLength);
        static::assertStringContainsString('64', $shortLength);
        static::assertStringContainsString('255', $longLength);
    }

    public function testTheDeclarationCacheKeyIsInsensitiveToColumnKeyOrder(): void
    {
        $anonymousEnumType = new class extends AbstractEnumType {
            /** @return array<string, string> */
            public static function getSqlDeclarationCache(): array
            {
                return static::$sqlDeclarationCache;
            }

            public function getEnumClass(): string
            {
                return TestBackedEnum::class;
            }
        };

        $postgresPlatform = new PostgreSQLPlatform();

        $firstOrder = $anonymousEnumType->getSQLDeclaration(['length' => 64, 'name' => 'status'], $postgresPlatform);
        $secondOrder = $anonymousEnumType->getSQLDeclaration(['name' => 'status', 'length' => 64], $postgresPlatform);

        static::assertSame($firstOrder, $secondOrder);
        static::assertCount(1, $anonymousEnumType::getSqlDeclarationCache());
    }

    public function testTheDeclarationCacheIsKeyedByTheConcreteTypeClass(): void
    {
        $testBackedEnumType = new TestBackedEnumType();
        $testSimpleEnumType = new TestSimpleEnumType();

        $backedDeclaration = $testBackedEnumType->getSQLDeclaration([], $this->mysqlPlatform);
        $simpleDeclaration = $testSimpleEnumType->getSQLDeclaration([], $this->mysqlPlatform);

        static::assertNotSame($backedDeclaration, $simpleDeclaration);
        static::assertStringContainsString('second_value', $backedDeclaration);
        static::assertStringNotContainsString('second_value', $simpleDeclaration);
        static::assertStringContainsString('beta', $simpleDeclaration);
    }

    public function testTheDeclarationCacheIsKeyedByPlatform(): void
    {
        $anonymousEnumType = new class extends AbstractEnumType {
            /** @return array<string, string> */
            public static function getSqlDeclarationCache(): array
            {
                return static::$sqlDeclarationCache;
            }

            public function getEnumClass(): string
            {
                return TestBackedEnum::class;
            }
        };

        $mysqlDeclaration = $anonymousEnumType->getSQLDeclaration([], $this->mysqlPlatform);
        $postgresDeclaration = $anonymousEnumType->getSQLDeclaration([], new PostgreSQLPlatform());

        static::assertNotSame($mysqlDeclaration, $postgresDeclaration);
        static::assertStringStartsWith('ENUM(', $mysqlDeclaration);
        static::assertCount(2, $anonymousEnumType::getSqlDeclarationCache());
    }

    public function testNegativeIntBackedEnumAcceptsAnIntegerFormattedString(): void
    {
        $testNegativeIntBackedEnumType = new TestNegativeIntBackedEnumType();

        static::assertSame(
            TestNegativeIntBackedEnum::below,
            $testNegativeIntBackedEnumType->convertToPHPValue('-3', $this->mysqlPlatform),
        );

        static::assertSame(
            TestNegativeIntBackedEnum::below,
            $testNegativeIntBackedEnumType->convertToPHPValue(-3, $this->mysqlPlatform),
        );

        /* the write side stays an int; only the read side has to cope with the string the server returns */
        static::assertSame(
            -3,
            $testNegativeIntBackedEnumType->convertToDatabaseValue(TestNegativeIntBackedEnum::below, $this->mysqlPlatform),
        );
    }

    public function testAnAliasConstantIsNotAcceptedAsACaseName(): void
    {
        $testSimpleEnumType = new TestSimpleEnumType();

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('invalid enum value');

        $testSimpleEnumType->convertToPHPValue('ALIAS', $this->mysqlPlatform);
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
}
