<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use PrecisionSoft\Doctrine\Type\SignedTinyintType;
use PrecisionSoft\Doctrine\Type\UnsignedTinyintType;

/** @internal */
final class TinyintVariantsTest extends TestCase
{
    /** @return iterable<string, array{int|string, int}> */
    public static function dataProviderSignedValueInRange(): iterable
    {
        yield 'lower bound' => [-128, -128];
        yield 'upper bound' => [127, 127];
        yield 'zero' => [0, 0];
        yield 'integer-formatted string' => ['127', 127];
        yield 'negative integer-formatted string' => ['-128', -128];
    }

    /** @return iterable<string, array{int|string}> */
    public static function dataProviderSignedValueOutOfRange(): iterable
    {
        yield 'below the lower bound' => [-129];
        yield 'above the upper bound' => [128];
        yield 'inside the unsigned half' => [200];
        yield 'string above the upper bound' => ['128'];
    }

    /** @return iterable<string, array{int|string, int}> */
    public static function dataProviderUnsignedValueInRange(): iterable
    {
        yield 'lower bound' => [0, 0];
        yield 'upper bound' => [255, 255];
        yield 'integer-formatted string' => ['255', 255];
    }

    /** @return iterable<string, array{int|string}> */
    public static function dataProviderUnsignedValueOutOfRange(): iterable
    {
        yield 'below the lower bound' => [-1];
        yield 'above the upper bound' => [256];
        yield 'inside the signed half' => [-128];
        yield 'string below the lower bound' => ['-1'];
    }

    public function testTheVariantsCarryTheirOwnTypeName(): void
    {
        static::assertSame('tinyint_signed', SignedTinyintType::getDefaultName());
        static::assertSame('tinyint_unsigned', UnsignedTinyintType::getDefaultName());
    }

    public function testTheSignedVariantAlwaysDeclaresASignedColumn(): void
    {
        $signedTinyintType = new SignedTinyintType();
        $mySqlPlatform = new MySQLPlatform();

        static::assertSame('TINYINT', $signedTinyintType->getSQLDeclaration([], $mySqlPlatform));
        static::assertSame('TINYINT', $signedTinyintType->getSQLDeclaration(['unsigned' => true], $mySqlPlatform));
    }

    public function testTheUnsignedVariantAlwaysDeclaresAnUnsignedColumn(): void
    {
        $unsignedTinyintType = new UnsignedTinyintType();
        $mySqlPlatform = new MySQLPlatform();

        static::assertSame('TINYINT UNSIGNED', $unsignedTinyintType->getSQLDeclaration([], $mySqlPlatform));
        static::assertSame(
            'TINYINT UNSIGNED',
            $unsignedTinyintType->getSQLDeclaration(['unsigned' => false], $mySqlPlatform),
        );
    }

    #[DataProvider('dataProviderSignedValueInRange')]
    public function testTheSignedVariantAcceptsItsRangeInBothDirections(int|string $value, int $expected): void
    {
        $signedTinyintType = new SignedTinyintType();
        $mySqlPlatform = new MySQLPlatform();

        static::assertSame($expected, $signedTinyintType->convertToDatabaseValue($value, $mySqlPlatform));
        static::assertSame($expected, $signedTinyintType->convertToPHPValue($value, $mySqlPlatform));
    }

    #[DataProvider('dataProviderSignedValueOutOfRange')]
    public function testTheSignedVariantRejectsValuesOutsideItsRangeOnWrite(int|string $value): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage(\sprintf('value `%s` is out of TINYINT range (-128 to 127)', $value));

        (new SignedTinyintType())->convertToDatabaseValue($value, new MySQLPlatform());
    }

    #[DataProvider('dataProviderSignedValueOutOfRange')]
    public function testTheSignedVariantRejectsValuesOutsideItsRangeOnRead(int|string $value): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage(\sprintf('value `%s` is out of TINYINT range (-128 to 127)', $value));

        (new SignedTinyintType())->convertToPHPValue($value, new MySQLPlatform());
    }

    #[DataProvider('dataProviderUnsignedValueInRange')]
    public function testTheUnsignedVariantAcceptsItsRangeInBothDirections(int|string $value, int $expected): void
    {
        $unsignedTinyintType = new UnsignedTinyintType();
        $mySqlPlatform = new MySQLPlatform();

        static::assertSame($expected, $unsignedTinyintType->convertToDatabaseValue($value, $mySqlPlatform));
        static::assertSame($expected, $unsignedTinyintType->convertToPHPValue($value, $mySqlPlatform));
    }

    #[DataProvider('dataProviderUnsignedValueOutOfRange')]
    public function testTheUnsignedVariantRejectsValuesOutsideItsRangeOnWrite(int|string $value): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage(\sprintf('value `%s` is out of TINYINT range (0 to 255)', $value));

        (new UnsignedTinyintType())->convertToDatabaseValue($value, new MySQLPlatform());
    }

    #[DataProvider('dataProviderUnsignedValueOutOfRange')]
    public function testTheUnsignedVariantRejectsValuesOutsideItsRangeOnRead(int|string $value): void
    {
        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage(\sprintf('value `%s` is out of TINYINT range (0 to 255)', $value));

        (new UnsignedTinyintType())->convertToPHPValue($value, new MySQLPlatform());
    }

    public function testAnOversizedStringKeepsItsOriginalValueInTheMessage(): void
    {
        $oversizedValue = '999999999999999999999';

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage(\sprintf('value `%s` is out of TINYINT range (0 to 255)', $oversizedValue));

        (new UnsignedTinyintType())->convertToDatabaseValue($oversizedValue, new MySQLPlatform());
    }

    /** the declaration is MySQL-only while conversion works everywhere, so the refusal has to say which platform arrived */
    public function testTheVariantsRefuseToDeclareAColumnOffMysql(): void
    {
        $postgreSqlPlatform = new PostgreSQLPlatform();

        foreach ([new SignedTinyintType(), new UnsignedTinyintType()] as $tinyintType) {
            try {
                $tinyintType->getSQLDeclaration([], $postgreSqlPlatform);

                static::fail(\sprintf('`%s` declared a column on postgresql', $tinyintType::class));
            } catch (Exception $exception) {
                static::assertSame(
                    \sprintf('this type only supports mysql, got `%s`', PostgreSQLPlatform::class),
                    $exception->getMessage(),
                );
            }
        }
    }

    public function testTheVariantsStillAcceptNull(): void
    {
        $mySqlPlatform = new MySQLPlatform();

        static::assertNull((new SignedTinyintType())->convertToDatabaseValue(null, $mySqlPlatform));
        static::assertNull((new UnsignedTinyintType())->convertToPHPValue(null, $mySqlPlatform));
    }
}
