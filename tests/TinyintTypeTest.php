<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use PrecisionSoft\Doctrine\Type\TinyintType;

class TinyintTypeTest extends TestCase
{
    private TinyintType $type;
    private MySQLPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = new TinyintType();
        $this->platform = new MySQLPlatform();
    }

    public function testGetDefaultName(): void
    {
        self::assertSame('tinyint', TinyintType::getDefaultName());
    }

    public function testGetName(): void
    {
        self::assertSame('tinyint', $this->type->getName());
    }

    public function testGetSqlDeclarationSigned(): void
    {
        $result = $this->type->getSQLDeclaration([], $this->platform);

        self::assertSame('tinyint', $result);
    }

    public function testGetSqlDeclarationUnsigned(): void
    {
        $result = $this->type->getSQLDeclaration(['unsigned' => true], $this->platform);

        self::assertSame('tinyint UNSIGNED', $result);
    }

    public function testGetSqlDeclarationNonMysqlThrows(): void
    {
        $this->expectException(Exception::class);

        $this->type->getSQLDeclaration([], new PostgreSQLPlatform());
    }

    public function testConvertToDatabaseValueNull(): void
    {
        $result = $this->type->convertToDatabaseValue(null, $this->platform);

        self::assertNull($result);
    }

    public function testConvertToDatabaseValueInt(): void
    {
        $result = $this->type->convertToDatabaseValue(42, $this->platform);

        self::assertSame(42, $result);
    }

    public function testConvertToDatabaseValueNegativeInt(): void
    {
        $result = $this->type->convertToDatabaseValue(-128, $this->platform);

        self::assertSame(-128, $result);
    }

    public function testConvertToDatabaseValueMaxUnsigned(): void
    {
        $result = $this->type->convertToDatabaseValue(255, $this->platform);

        self::assertSame(255, $result);
    }

    public function testConvertToDatabaseValueString(): void
    {
        $result = $this->type->convertToDatabaseValue('100', $this->platform);

        self::assertSame('100', $result);
    }

    public function testConvertToDatabaseValueNegativeString(): void
    {
        $result = $this->type->convertToDatabaseValue('-50', $this->platform);

        self::assertSame('-50', $result);
    }

    public function testConvertToDatabaseValueOutOfRangeHighThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('out of tinyint range');

        $this->type->convertToDatabaseValue(256, $this->platform);
    }

    public function testConvertToDatabaseValueOutOfRangeLowThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('out of tinyint range');

        $this->type->convertToDatabaseValue(-129, $this->platform);
    }

    public function testConvertToDatabaseValueOutOfRangeStringThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('out of tinyint range');

        $this->type->convertToDatabaseValue('999', $this->platform);
    }

    public function testConvertToDatabaseValueInvalidTypeThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->type->convertToDatabaseValue(1.5, $this->platform);
    }

    public function testConvertToDatabaseValueInvalidStringThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('expected integer');

        $this->type->convertToDatabaseValue('abc', $this->platform);
    }

    public function testConvertToDatabaseValueObjectThrows(): void
    {
        $this->expectException(InvalidTypeValueException::class);

        $this->type->convertToDatabaseValue(new \stdClass(), $this->platform);
    }

    public function testConvertToPhpValueNull(): void
    {
        $result = $this->type->convertToPHPValue(null, $this->platform);

        self::assertNull($result);
    }

    public function testConvertToPhpValueInt(): void
    {
        $result = $this->type->convertToPHPValue(42, $this->platform);

        self::assertSame(42, $result);
    }

    public function testConvertToPhpValueString(): void
    {
        $result = $this->type->convertToPHPValue('100', $this->platform);

        self::assertSame(100, $result);
    }
}
