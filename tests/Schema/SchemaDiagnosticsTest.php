<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Schema;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Schema\SchemaDiagnostics;

/** @internal */
final class SchemaDiagnosticsTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function dataProviderPlainDatabaseType(): iterable
    {
        yield 'string' => ['string'];
        yield 'varchar' => ['varchar'];
        yield 'integer' => ['integer'];
        yield 'simple array' => ['simple_array'];
    }

    /** @return iterable<string, array{string}> */
    public static function dataProviderConstrainedDatabaseType(): iterable
    {
        yield 'enum' => ['enum'];
        yield 'set' => ['set'];
        yield 'uppercase enum' => ['ENUM'];
    }

    #[DataProvider('dataProviderConstrainedDatabaseType')]
    public function testAnEnumOrSetColumnIsSteeredAwayFromAGlobalMapping(string $databaseType): void
    {
        $diagnostic = (new SchemaDiagnostics())->diagnose('orders', 'status', $databaseType);

        static::assertNotNull($diagnostic);
        static::assertSame('orders', $diagnostic->table);
        static::assertSame('status', $diagnostic->column);
        static::assertSame('warning', $diagnostic->severity);
        static::assertStringContainsString('getMappedDatabaseTypes()', $diagnostic->message);
        static::assertStringContainsString('AbstractPortableEnumType', $diagnostic->message);
    }

    public function testASignedTinyintColumnPointsAtTheSignedVariant(): void
    {
        $diagnostic = (new SchemaDiagnostics())->diagnose('orders', 'priority', 'tinyint');

        static::assertNotNull($diagnostic);
        static::assertSame('warning', $diagnostic->severity);
        static::assertStringContainsString('SignedTinyintType', $diagnostic->message);
    }

    public function testAnUnsignedTinyintColumnPointsAtTheUnsignedVariant(): void
    {
        $diagnostic = (new SchemaDiagnostics())->diagnose('orders', 'flags', 'tinyint', true);

        static::assertNotNull($diagnostic);
        static::assertSame('warning', $diagnostic->severity);
        static::assertStringContainsString('UnsignedTinyintType', $diagnostic->message);
    }

    #[DataProvider('dataProviderPlainDatabaseType')]
    public function testAPlainColumnIsNotReported(string $databaseType): void
    {
        static::assertNull((new SchemaDiagnostics())->diagnose('orders', 'title', $databaseType));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function dataProviderIntrospectablePlatform(): iterable
    {
        yield 'mysql' => [['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']];
        yield 'mariadb' => [['driver' => 'pdo_mysql', 'serverVersion' => '11.4.0-MariaDB']];
        yield 'postgresql' => [['driver' => 'pdo_pgsql', 'serverVersion' => '17']];
    }

    /** @param array<string, mixed> $connectionParameters */
    #[DataProvider('dataProviderIntrospectablePlatform')]
    public function testAPlatformWithAnIntrospectionQueryIsSupported(array $connectionParameters): void
    {
        $connection = DriverManager::getConnection($connectionParameters);

        try {
            static::assertTrue((new SchemaDiagnostics())->supports($connection));
        } finally {
            $connection->close();
        }
    }

    public function testAPlatformWithoutAnIntrospectionQueryIsReportedAsUnsupported(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schemaDiagnostics = new SchemaDiagnostics();

        try {
            $connection->executeStatement('CREATE TABLE orders (status VARCHAR(32), priority TINYINT)');

            static::assertFalse($schemaDiagnostics->supports($connection));
            static::assertSame([], $schemaDiagnostics->inspect($connection));
        } finally {
            $connection->close();
        }
    }
}
