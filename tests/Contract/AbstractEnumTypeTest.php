<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Contract;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnumType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnumType;

class AbstractEnumTypeTest extends TestCase
{
    private MySQLPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = new MySQLPlatform();
    }

    public function testBackedEnumConvertToDatabaseValueNull(): void
    {
        $type = new TestBackedEnumType();
        $result = $type->convertToDatabaseValue(null, $this->platform);

        self::assertNull($result);
    }

    public function testBackedEnumConvertToDatabaseValue(): void
    {
        $type = new TestBackedEnumType();
        $result = $type->convertToDatabaseValue(TestBackedEnum::first, $this->platform);

        self::assertSame('first_value', $result);
    }

    public function testBackedEnumConvertToPhpValueNull(): void
    {
        $type = new TestBackedEnumType();
        $result = $type->convertToPHPValue(null, $this->platform);

        self::assertNull($result);
    }

    public function testBackedEnumConvertToPhpValue(): void
    {
        $type = new TestBackedEnumType();
        $result = $type->convertToPHPValue('first_value', $this->platform);

        self::assertSame(TestBackedEnum::first, $result);
    }

    public function testSimpleEnumConvertToDatabaseValue(): void
    {
        $type = new TestSimpleEnumType();
        $result = $type->convertToDatabaseValue(TestSimpleEnum::alpha, $this->platform);

        self::assertSame('alpha', $result);
    }

    public function testSimpleEnumConvertToPhpValue(): void
    {
        $type = new TestSimpleEnumType();
        $result = $type->convertToPHPValue('alpha', $this->platform);

        self::assertSame(TestSimpleEnum::alpha, $result);
    }

    public function testBackedEnumGetSqlDeclarationMysql(): void
    {
        $type = new TestBackedEnumType();
        $result = $type->getSQLDeclaration([], $this->platform);

        self::assertStringStartsWith('ENUM(', $result);
        self::assertStringContainsString('first_value', $result);
        self::assertStringContainsString('second_value', $result);
        self::assertStringContainsString('third_value', $result);
    }

    public function testSimpleEnumGetSqlDeclarationMysql(): void
    {
        $type = new TestSimpleEnumType();
        $result = $type->getSQLDeclaration([], $this->platform);

        self::assertStringStartsWith('ENUM(', $result);
        self::assertStringContainsString('alpha', $result);
        self::assertStringContainsString('beta', $result);
        self::assertStringContainsString('gamma', $result);
    }

    public function testGetSqlDeclarationNonMysql(): void
    {
        $type = new TestBackedEnumType();
        $result = $type->getSQLDeclaration([], new PostgreSQLPlatform());

        self::assertStringNotContainsString('ENUM(', $result);
    }

    public function testGetValues(): void
    {
        $type = new TestBackedEnumType();
        $values = $type->getValues();

        self::assertCount(3, $values);
        self::assertSame(TestBackedEnum::first, $values[0]);
        self::assertSame(TestBackedEnum::second, $values[1]);
        self::assertSame(TestBackedEnum::third, $values[2]);
    }
}
