<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test;

use DateTime;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use PrecisionSoft\Doctrine\Type\DateTimeType;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;

/** @internal */
final class DateTimeTypeTest extends AbstractTestCase
{
    private DateTimeType $dateTimeType;
    private MySQLPlatform $mysqlPlatform;

    public static function getMockDto(): MockDto
    {
        return new MockDto(stdClass::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dateTimeType = new DateTimeType();
        $this->mysqlPlatform = new MySQLPlatform();
    }

    public function testGetSqlDeclarationDefault(): void
    {
        $sqlDeclaration = $this->dateTimeType->getSQLDeclaration([], $this->mysqlPlatform);

        static::assertSame('DATETIME', $sqlDeclaration);
    }

    public function testGetSqlDeclarationWithUpdateMysql(): void
    {
        $sqlDeclaration = $this->dateTimeType->getSQLDeclaration(['update' => true], $this->mysqlPlatform);

        static::assertSame('DATETIME ON UPDATE CURRENT_TIMESTAMP', $sqlDeclaration);
    }

    public function testGetSqlDeclarationWithEmptyUpdateMysql(): void
    {
        $sqlDeclaration = $this->dateTimeType->getSQLDeclaration(['update' => ''], $this->mysqlPlatform);

        static::assertSame('DATETIME', $sqlDeclaration);
    }

    public function testGetSqlDeclarationWithNullUpdateMysql(): void
    {
        $sqlDeclaration = $this->dateTimeType->getSQLDeclaration(['update' => null], $this->mysqlPlatform);

        static::assertSame('DATETIME', $sqlDeclaration);
    }

    public function testGetSqlDeclarationWithUpdateNonMysql(): void
    {
        $sqlDeclaration = $this->dateTimeType->getSQLDeclaration(
            ['update' => 'CURRENT_TIMESTAMP'],
            new PostgreSQLPlatform(),
        );

        static::assertStringNotContainsString('ON UPDATE', $sqlDeclaration);
    }

    public function testConvertToDatabaseValueNull(): void
    {
        $databaseValue = $this->dateTimeType->convertToDatabaseValue(null, $this->mysqlPlatform);

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        static::assertNull($databaseValue);
    }

    public function testConvertToDatabaseValueDateTime(): void
    {
        $dateTime = new DateTime('2026-04-14 12:00:00');

        $databaseValue = $this->dateTimeType->convertToDatabaseValue($dateTime, $this->mysqlPlatform);

        static::assertSame('2026-04-14 12:00:00', $databaseValue);
    }

    public function testConvertToDatabaseValueInvalidTypeThrows(): void
    {
        $this->expectException(InvalidType::class);

        $this->dateTimeType->convertToDatabaseValue('not-a-datetime', $this->mysqlPlatform);
    }

    public function testConvertToPhpValueNull(): void
    {
        $phpValue = $this->dateTimeType->convertToPHPValue(null, $this->mysqlPlatform);

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        static::assertNull($phpValue);
    }

    public function testConvertToPhpValueString(): void
    {
        $phpValue = $this->dateTimeType->convertToPHPValue('2026-04-14 12:00:00', $this->mysqlPlatform);

        static::assertInstanceOf(DateTime::class, $phpValue);
        static::assertSame('2026-04-14 12:00:00', $phpValue->format('Y-m-d H:i:s'));
    }

    public function testConvertToPhpValueDateTimePassthrough(): void
    {
        $dateTime = new DateTime('2026-04-14 12:00:00');

        $phpValue = $this->dateTimeType->convertToPHPValue($dateTime, $this->mysqlPlatform);

        static::assertSame($dateTime, $phpValue);
    }

    public function testConvertToPhpValueInvalidFormatThrows(): void
    {
        $this->expectException(InvalidFormat::class);

        $this->dateTimeType->convertToPHPValue('not-valid-datetime', $this->mysqlPlatform);
    }
}
