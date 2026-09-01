<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Schema\Diagnostic;
use PrecisionSoft\Doctrine\Type\Schema\SchemaDiagnostics;
use PrecisionSoft\Doctrine\Type\Test\Utility\IntegrationDatabase;
use PrecisionSoft\Doctrine\Type\Test\Utility\SkipIntegrationException;

/** @internal */
#[Group('integration')]
final class SchemaDiagnosticsFunctionalTest extends TestCase
{
    protected const TABLE_NAME = 'schema_diagnostics_probe';

    protected const POSTGRESQL_ENUM_TYPE_NAME = 'schema_diagnostics_status';

    protected const VIEW_NAME = 'schema_diagnostics_probe_view';

    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        if (null !== $this->connection) {
            $this->connection->executeStatement('DROP VIEW IF EXISTS ' . static::VIEW_NAME);
            $this->connection->executeStatement('DROP TABLE IF EXISTS ' . static::TABLE_NAME);

            if (true === $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $this->connection->executeStatement('DROP TYPE IF EXISTS ' . static::POSTGRESQL_ENUM_TYPE_NAME);
            }

            $this->connection->close();
            $this->connection = null;
        }

        parent::tearDown();
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderMySqlEngine')]
    public function testEveryUnmappedMySqlColumnIsReportedAndNothingElseIs(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);

        $connection->executeStatement(\sprintf(
            "CREATE TABLE %s (
                status ENUM('first_value','second_value'),
                tags SET('first_value','second_value'),
                priority TINYINT,
                flags TINYINT UNSIGNED,
                title VARCHAR(32)
            )",
            static::TABLE_NAME,
        ));

        $connection->executeStatement(\sprintf(
            'CREATE VIEW %s AS SELECT status, priority FROM %s',
            static::VIEW_NAME,
            static::TABLE_NAME,
        ));

        $diagnostics = $this->inspectProbeTable($connection);

        static::assertSame(['status', 'tags', 'priority', 'flags'], \array_keys($diagnostics));
        static::assertSame([], $this->inspectTable($connection, static::VIEW_NAME));
        static::assertSame('enum', $diagnostics['status']->databaseType);
        static::assertSame('set', $diagnostics['tags']->databaseType);
        static::assertStringContainsString('AbstractPortableEnumType', $diagnostics['status']->message);
        static::assertStringContainsString('SignedTinyintType', $diagnostics['priority']->message);
        static::assertStringContainsString('UnsignedTinyintType', $diagnostics['flags']->message);
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderPostgreSqlEngine')]
    public function testAPostgreSqlEnumTypeIsReported(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);

        $connection->executeStatement('DROP TABLE IF EXISTS ' . static::TABLE_NAME);
        $connection->executeStatement('DROP TYPE IF EXISTS ' . static::POSTGRESQL_ENUM_TYPE_NAME);
        $connection->executeStatement(\sprintf(
            "CREATE TYPE %s AS ENUM ('first_value','second_value')",
            static::POSTGRESQL_ENUM_TYPE_NAME,
        ));
        $connection->executeStatement(\sprintf(
            'CREATE TABLE %s (status %s, title VARCHAR(32))',
            static::TABLE_NAME,
            static::POSTGRESQL_ENUM_TYPE_NAME,
        ));

        $connection->executeStatement(\sprintf(
            'CREATE VIEW %s AS SELECT status FROM %s',
            static::VIEW_NAME,
            static::TABLE_NAME,
        ));

        $diagnostics = $this->inspectProbeTable($connection);

        static::assertSame(['status'], \array_keys($diagnostics));
        static::assertSame([], $this->inspectTable($connection, static::VIEW_NAME));
        static::assertSame('enum', $diagnostics['status']->databaseType);
        static::assertStringContainsString('AbstractPortableEnumType', $diagnostics['status']->message);
    }

    private function boot(string $environmentVariable): Connection
    {
        try {
            $this->connection = IntegrationDatabase::createConnection($environmentVariable);
        } catch (SkipIntegrationException $skipIntegrationException) {
            static::markTestSkipped($skipIntegrationException->getMessage());
        }

        $this->connection->executeStatement('DROP VIEW IF EXISTS ' . static::VIEW_NAME);
        $this->connection->executeStatement('DROP TABLE IF EXISTS ' . static::TABLE_NAME);

        return $this->connection;
    }

    /** @return array<string, Diagnostic> */
    private function inspectProbeTable(Connection $connection): array
    {
        return $this->inspectTable($connection, static::TABLE_NAME);
    }

    /** @return array<string, Diagnostic> */
    private function inspectTable(Connection $connection, string $tableName): array
    {
        $diagnostics = [];

        foreach ((new SchemaDiagnostics())->inspect($connection) as $diagnostic) {
            if ($tableName === $diagnostic->table) {
                $diagnostics[$diagnostic->column] = $diagnostic;
            }
        }

        return $diagnostics;
    }
}
