<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Contract;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnumType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnumType;
use PrecisionSoft\Doctrine\Type\Contract\AbstractPhpEnumType;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;

class AbstractEnumTypeTest extends AbstractTestCase
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

    public function testBackedEnumConvertToDatabaseValueNull(): void
    {
        $testBackedEnumType = new TestBackedEnumType();
        $databaseValue = $testBackedEnumType->convertToDatabaseValue(null, $this->mysqlPlatform);

        self::assertNull($databaseValue);
    }

    public function testBackedEnumConvertToDatabaseValue(): void
    {
        $testBackedEnumType = new TestBackedEnumType();
        $databaseValue = $testBackedEnumType->convertToDatabaseValue(TestBackedEnum::first, $this->mysqlPlatform);

        self::assertSame('first_value', $databaseValue);
    }

    public function testBackedEnumConvertToPhpValueNull(): void
    {
        $testBackedEnumType = new TestBackedEnumType();
        $phpValue = $testBackedEnumType->convertToPHPValue(null, $this->mysqlPlatform);

        self::assertNull($phpValue);
    }

    public function testBackedEnumConvertToPhpValue(): void
    {
        $testBackedEnumType = new TestBackedEnumType();
        $phpValue = $testBackedEnumType->convertToPHPValue('first_value', $this->mysqlPlatform);

        self::assertSame(TestBackedEnum::first, $phpValue);
    }

    public function testSimpleEnumConvertToDatabaseValue(): void
    {
        $testSimpleEnumType = new TestSimpleEnumType();
        $databaseValue = $testSimpleEnumType->convertToDatabaseValue(TestSimpleEnum::alpha, $this->mysqlPlatform);

        self::assertSame('alpha', $databaseValue);
    }

    public function testSimpleEnumConvertToPhpValue(): void
    {
        $testSimpleEnumType = new TestSimpleEnumType();
        $phpValue = $testSimpleEnumType->convertToPHPValue('alpha', $this->mysqlPlatform);

        self::assertSame(TestSimpleEnum::alpha, $phpValue);
    }

    public function testBackedEnumGetSqlDeclarationMysql(): void
    {
        $testBackedEnumType = new TestBackedEnumType();
        $sqlDeclaration = $testBackedEnumType->getSQLDeclaration([], $this->mysqlPlatform);

        self::assertStringStartsWith('ENUM(', $sqlDeclaration);
        self::assertStringContainsString('first_value', $sqlDeclaration);
        self::assertStringContainsString('second_value', $sqlDeclaration);
        self::assertStringContainsString('third_value', $sqlDeclaration);
    }

    public function testSimpleEnumGetSqlDeclarationMysql(): void
    {
        $testSimpleEnumType = new TestSimpleEnumType();
        $sqlDeclaration = $testSimpleEnumType->getSQLDeclaration([], $this->mysqlPlatform);

        self::assertStringStartsWith('ENUM(', $sqlDeclaration);
        self::assertStringContainsString('alpha', $sqlDeclaration);
        self::assertStringContainsString('beta', $sqlDeclaration);
        self::assertStringContainsString('gamma', $sqlDeclaration);
    }

    public function testGetSqlDeclarationNonMysql(): void
    {
        $testBackedEnumType = new TestBackedEnumType();
        $sqlDeclaration = $testBackedEnumType->getSQLDeclaration([], new PostgreSQLPlatform());

        self::assertStringNotContainsString('ENUM(', $sqlDeclaration);
    }

    public function testGetValues(): void
    {
        $testBackedEnumType = new TestBackedEnumType();
        $enumValues = $testBackedEnumType->getValues();

        self::assertCount(3, $enumValues);
        self::assertSame(TestBackedEnum::first, $enumValues[0]);
        self::assertSame(TestBackedEnum::second, $enumValues[1]);
        self::assertSame(TestBackedEnum::third, $enumValues[2]);
    }
}
