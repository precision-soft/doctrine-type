<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Contract;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Contract\AbstractPhpEnumType;
use PrecisionSoft\Doctrine\Type\Contract\AbstractPortableEnumType;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestPortableEnumType;

/** @internal */
final class AbstractPortableEnumTypeTest extends TestCase
{
    /** @return iterable<string, array{AbstractPlatform}> */
    public static function dataProviderConstrainedPlatform(): iterable
    {
        yield 'postgresql' => [new PostgreSQLPlatform()];
        yield 'sqlite' => [new SQLitePlatform()];
    }

    #[DataProvider('dataProviderConstrainedPlatform')]
    public function testAConstrainedPlatformGetsAVarcharCarryingEveryEnumValue(AbstractPlatform $platform): void
    {
        $sqlDeclaration = (new TestPortableEnumType())->getSQLDeclaration(
            ['name' => 'status', 'length' => 32],
            $platform,
        );

        static::assertStringContainsString('VARCHAR(32)', $sqlDeclaration);
        static::assertStringContainsString('CHECK (status IN (', $sqlDeclaration);
        static::assertStringContainsString("'first_value'", $sqlDeclaration);
        static::assertStringContainsString("'second_value'", $sqlDeclaration);
        static::assertStringContainsString("'third_value'", $sqlDeclaration);
    }

    /** DBAL hands the declaration the name it already quoted, so the constraint must use it verbatim */
    #[DataProvider('dataProviderConstrainedPlatform')]
    public function testTheConstraintUsesTheColumnNameAsDbalQuotedIt(AbstractPlatform $platform): void
    {
        $testPortableEnumType = new TestPortableEnumType();

        static::assertStringContainsString(
            'CHECK ("order" IN (',
            $testPortableEnumType->getSQLDeclaration(['name' => '"order"', 'length' => 32], $platform),
            'a quoted reserved word must not be quoted a second time',
        );
        static::assertStringContainsString(
            'CHECK (Status IN (',
            $testPortableEnumType->getSQLDeclaration(['name' => 'Status', 'length' => 32], $platform),
            'an unquoted name is folded by the server, so the constraint must stay unquoted too',
        );
    }

    #[DataProvider('dataProviderConstrainedPlatform')]
    public function testAConstrainedPlatformRequiresTheColumnName(AbstractPlatform $platform): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('portable enum declarations require the column `name`');

        (new TestPortableEnumType())->getSQLDeclaration([], $platform);
    }

    public function testMysqlKeepsItsNativeEnumDeclaration(): void
    {
        $sqlDeclaration = (new TestPortableEnumType())->getSQLDeclaration(
            ['name' => 'status', 'length' => 32],
            new MySQLPlatform(),
        );

        static::assertSame("ENUM('first_value','second_value','third_value')", $sqlDeclaration);
        static::assertStringNotContainsString('CHECK', $sqlDeclaration);
    }

    public function testTheConstrainedDeclarationGoesThroughTheSharedCache(): void
    {
        $anonymousPortableEnumType = new class extends AbstractPortableEnumType {
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

        $postgreSqlPlatform = new PostgreSQLPlatform();
        $column = ['name' => 'status', 'length' => 32];

        $firstDeclaration = $anonymousPortableEnumType->getSQLDeclaration($column, $postgreSqlPlatform);
        $secondDeclaration = $anonymousPortableEnumType->getSQLDeclaration($column, $postgreSqlPlatform);

        static::assertSame($firstDeclaration, $secondDeclaration);
        static::assertStringContainsString('CHECK', $firstDeclaration);
        static::assertCount(1, $anonymousPortableEnumType::getSqlDeclarationCache());
    }

    protected function tearDown(): void
    {
        AbstractPhpEnumType::clearCache();

        parent::tearDown();
    }
}
