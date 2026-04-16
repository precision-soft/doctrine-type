<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use PrecisionSoft\Doctrine\Type\TinyintType;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;

class TinyintTypeTest extends AbstractTestCase
{
    private TinyintType $tinyintType;
    private MySQLPlatform $mysqlPlatform;

    public static function getMockDto(): MockDto
    {
        return new MockDto(stdClass::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tinyintType = new TinyintType();
        $this->mysqlPlatform = new MySQLPlatform();
    }

    public function testGetDefaultName(): void
    {
        self::assertSame('tinyint', TinyintType::getDefaultName());
    }

    public function testGetSqlDeclarationSigned(): void
    {
        $sqlDeclaration = $this->tinyintType->getSQLDeclaration([], $this->mysqlPlatform);

        self::assertSame('TINYINT', $sqlDeclaration);
    }

    public function testGetSqlDeclarationUnsigned(): void
    {
        $sqlDeclaration = $this->tinyintType->getSQLDeclaration(['unsigned' => true], $this->mysqlPlatform);

        self::assertSame('TINYINT UNSIGNED', $sqlDeclaration);
    }

    public function testGetSqlDeclarationNonMysqlThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);

        $this->tinyintType->getSQLDeclaration([], new PostgreSQLPlatform());
    }

    public function testConvertToDatabaseValueNull(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue(null, $this->mysqlPlatform);

        self::assertNull($databaseValue);
    }

    public function testConvertToDatabaseValueInt(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue(42, $this->mysqlPlatform);

        self::assertSame(42, $databaseValue);
    }

    public function testConvertToDatabaseValueNegativeInt(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue(-128, $this->mysqlPlatform);

        self::assertSame(-128, $databaseValue);
    }

    public function testConvertToDatabaseValueString(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue('100', $this->mysqlPlatform);

        self::assertSame(100, $databaseValue);
    }

    public function testConvertToDatabaseValuePositiveSignedString(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue('+42', $this->mysqlPlatform);

        self::assertSame(42, $databaseValue);
    }

    public function testConvertToDatabaseValueNegativeString(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue('-50', $this->mysqlPlatform);

        self::assertSame(-50, $databaseValue);
    }

    public function testConvertToDatabaseValueOutOfRangeHighThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('out of TINYINT range');

        $this->tinyintType->convertToDatabaseValue(256, $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueOutOfRangeLowThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('out of TINYINT range');

        $this->tinyintType->convertToDatabaseValue(-129, $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueOutOfRangeStringThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('out of TINYINT range');

        $this->tinyintType->convertToDatabaseValue('999', $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueLargeStringErrorUsesOriginalValue(): void
    {
        $oversizedValue = '99999999999999999999';

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage(\sprintf('value `%s` is out of TINYINT range', $oversizedValue));

        $this->tinyintType->convertToDatabaseValue($oversizedValue, $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueInvalidTypeThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->tinyintType->convertToDatabaseValue(1.5, $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueInvalidStringThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->tinyintType->convertToDatabaseValue('abc', $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueObjectThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);

        $this->tinyintType->convertToDatabaseValue(new stdClass(), $this->mysqlPlatform);
    }

    public function testConvertToPhpValueNull(): void
    {
        $phpValue = $this->tinyintType->convertToPHPValue(null, $this->mysqlPlatform);

        self::assertNull($phpValue);
    }

    public function testConvertToPhpValueInt(): void
    {
        $phpValue = $this->tinyintType->convertToPHPValue(42, $this->mysqlPlatform);

        self::assertSame(42, $phpValue);
    }

    public function testConvertToPhpValueString(): void
    {
        $phpValue = $this->tinyintType->convertToPHPValue('100', $this->mysqlPlatform);

        self::assertSame(100, $phpValue);
    }

    public function testGetBindingType(): void
    {
        $parameterType = $this->tinyintType->getBindingType();

        self::assertSame(ParameterType::INTEGER, $parameterType);
    }

    public function testConvertToDatabaseValueBoundaryMinSigned(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue(-128, $this->mysqlPlatform);

        self::assertSame(-128, $databaseValue);
    }

    public function testConvertToDatabaseValueBoundaryMinSignedMinus1Throws(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('out of TINYINT range');

        $this->tinyintType->convertToDatabaseValue(-129, $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueBoundaryMaxSigned(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue(127, $this->mysqlPlatform);

        self::assertSame(127, $databaseValue);
    }

    public function testConvertToDatabaseValueBoundaryMaxUnsigned(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue(255, $this->mysqlPlatform);

        self::assertSame(255, $databaseValue);
    }

    public function testConvertToDatabaseValueBoundaryMaxUnsignedPlus1Throws(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('out of TINYINT range');

        $this->tinyintType->convertToDatabaseValue(256, $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueZero(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue(0, $this->mysqlPlatform);

        self::assertSame(0, $databaseValue);
    }

    public function testConvertToDatabaseValueStringBoundaryMinSigned(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue('-128', $this->mysqlPlatform);

        self::assertSame(-128, $databaseValue);
    }

    public function testConvertToDatabaseValueStringBoundaryMaxSigned(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue('127', $this->mysqlPlatform);

        self::assertSame(127, $databaseValue);
    }

    public function testConvertToDatabaseValueStringBoundaryMaxUnsigned(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue('255', $this->mysqlPlatform);

        self::assertSame(255, $databaseValue);
    }

    public function testConvertToDatabaseValueStringBoundaryMaxUnsignedPlus1Throws(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('out of TINYINT range');

        $this->tinyintType->convertToDatabaseValue('256', $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueStringZero(): void
    {
        $databaseValue = $this->tinyintType->convertToDatabaseValue('0', $this->mysqlPlatform);

        self::assertSame(0, $databaseValue);
    }

    public function testConvertToDatabaseValueBoolThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->tinyintType->convertToDatabaseValue(true, $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueArrayThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->tinyintType->convertToDatabaseValue([], $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueEmptyStringThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->tinyintType->convertToDatabaseValue('', $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueStringWithSpacesThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->tinyintType->convertToDatabaseValue(' 42 ', $this->mysqlPlatform);
    }

    public function testConvertToDatabaseValueStringWithDecimalThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->tinyintType->convertToDatabaseValue('1.5', $this->mysqlPlatform);
    }

    public function testConvertToPhpValueZero(): void
    {
        $phpValue = $this->tinyintType->convertToPHPValue(0, $this->mysqlPlatform);

        self::assertSame(0, $phpValue);
    }

    public function testConvertToPhpValueNegativeString(): void
    {
        $phpValue = $this->tinyintType->convertToPHPValue('-50', $this->mysqlPlatform);

        self::assertSame(-50, $phpValue);
    }

    public function testConvertToPhpValueFloatThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->tinyintType->convertToPHPValue(1.5, $this->mysqlPlatform);
    }

    public function testConvertToPhpValueBoolThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->tinyintType->convertToPHPValue(true, $this->mysqlPlatform);
    }

    public function testConvertToPhpValueArrayThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->tinyintType->convertToPHPValue([], $this->mysqlPlatform);
    }

    public function testConvertToPhpValueObjectThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->tinyintType->convertToPHPValue(new stdClass(), $this->mysqlPlatform);
    }

    public function testConvertToPhpValueInvalidStringThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->tinyintType->convertToPHPValue('abc', $this->mysqlPlatform);
    }

    public function testGetSqlDeclarationUnsignedFalse(): void
    {
        $sqlDeclaration = $this->tinyintType->getSQLDeclaration(['unsigned' => false], $this->mysqlPlatform);

        self::assertSame('TINYINT', $sqlDeclaration);
    }

    public function testConvertToPhpValueStringBoundaryMinSigned(): void
    {
        $phpValue = $this->tinyintType->convertToPHPValue('-128', $this->mysqlPlatform);

        self::assertSame(-128, $phpValue);
    }

    public function testConvertToPhpValueStringBoundaryMinSignedMinus1Throws(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('out of TINYINT range');

        $this->tinyintType->convertToPHPValue('-129', $this->mysqlPlatform);
    }

    public function testConvertToPhpValueStringBoundaryMaxUnsigned(): void
    {
        $phpValue = $this->tinyintType->convertToPHPValue('255', $this->mysqlPlatform);

        self::assertSame(255, $phpValue);
    }

    public function testConvertToPhpValueStringBoundaryMaxUnsignedPlus1Throws(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('out of TINYINT range');

        $this->tinyintType->convertToPHPValue('256', $this->mysqlPlatform);
    }
}
