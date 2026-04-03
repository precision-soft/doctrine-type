<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PrecisionSoft\Doctrine\Type\DateTimeType;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;

class DateTimeTypeTest extends AbstractTestCase
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

        self::assertSame('DATETIME', $sqlDeclaration);
    }

    public function testGetSqlDeclarationWithUpdateMysql(): void
    {
        $sqlDeclaration = $this->dateTimeType->getSQLDeclaration(['update' => true], $this->mysqlPlatform);

        self::assertSame('DATETIME ON UPDATE CURRENT_TIMESTAMP', $sqlDeclaration);
    }

    public function testGetSqlDeclarationWithEmptyUpdateMysql(): void
    {
        $sqlDeclaration = $this->dateTimeType->getSQLDeclaration(['update' => ''], $this->mysqlPlatform);

        self::assertSame('DATETIME', $sqlDeclaration);
    }

    public function testGetSqlDeclarationWithNullUpdateMysql(): void
    {
        $sqlDeclaration = $this->dateTimeType->getSQLDeclaration(['update' => null], $this->mysqlPlatform);

        self::assertSame('DATETIME', $sqlDeclaration);
    }

    public function testGetSqlDeclarationWithUpdateNonMysql(): void
    {
        $sqlDeclaration = $this->dateTimeType->getSQLDeclaration(
            ['update' => 'CURRENT_TIMESTAMP'],
            new PostgreSQLPlatform(),
        );

        self::assertStringNotContainsString('ON UPDATE', $sqlDeclaration);
    }
}
