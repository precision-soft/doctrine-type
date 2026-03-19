<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\DateTimeType;

class DateTimeTypeTest extends TestCase
{
    private DateTimeType $type;
    private MySQLPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = new DateTimeType();
        $this->platform = new MySQLPlatform();
    }

    public function testGetSqlDeclarationDefault(): void
    {
        $result = $this->type->getSQLDeclaration([], $this->platform);

        self::assertSame('DATETIME', $result);
    }

    public function testGetSqlDeclarationWithUpdateMysql(): void
    {
        $result = $this->type->getSQLDeclaration(['update' => 'CURRENT_TIMESTAMP'], $this->platform);

        self::assertSame('DATETIME ON UPDATE CURRENT_TIMESTAMP', $result);
    }

    public function testGetSqlDeclarationWithEmptyUpdateMysql(): void
    {
        $result = $this->type->getSQLDeclaration(['update' => ''], $this->platform);

        self::assertSame('DATETIME', $result);
    }

    public function testGetSqlDeclarationWithNullUpdateMysql(): void
    {
        $result = $this->type->getSQLDeclaration(['update' => null], $this->platform);

        self::assertSame('DATETIME', $result);
    }

    public function testGetSqlDeclarationWithUpdateNonMysql(): void
    {
        $result = $this->type->getSQLDeclaration(
            ['update' => 'CURRENT_TIMESTAMP'],
            new PostgreSQLPlatform(),
        );

        self::assertStringNotContainsString('ON UPDATE', $result);
    }
}
