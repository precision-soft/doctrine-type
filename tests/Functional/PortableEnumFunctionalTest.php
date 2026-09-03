<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Contract\AbstractPhpEnumType;
use PrecisionSoft\Doctrine\Type\Test\Utility\IntegrationDatabase;
use PrecisionSoft\Doctrine\Type\Test\Utility\SkipIntegrationException;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestPortableEnumType;

/** @internal */
#[Group('integration')]
final class PortableEnumFunctionalTest extends TestCase
{
    protected const TABLE_NAME = 'portable_enum_probe';

    private ?Connection $connection = null;

    public function testSqliteEnforcesThePortableConstraint(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $this->assertTheServerRejectsAValueOutsideTheEnum($this->connection);
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderPostgreSqlEngine')]
    public function testPostgresqlEnforcesThePortableConstraint(string $environmentVariable): void
    {
        $this->assertTheServerRejectsAValueOutsideTheEnum($this->boot($environmentVariable));
    }

    public function testSqliteConstrainsAQuotedReservedColumnName(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $this->assertTheServerRejectsAValueOutsideTheEnum($this->connection, '"order"');
    }

    public function testSqliteConstrainsAMixedCaseColumnName(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $this->assertTheServerRejectsAValueOutsideTheEnum($this->connection, 'Status');
    }

    /** a quoted name reaches the declaration already quoted; quoting it again names a column that does not exist */
    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderPostgreSqlEngine')]
    public function testPostgresqlConstrainsAQuotedReservedColumnName(string $environmentVariable): void
    {
        $this->assertTheServerRejectsAValueOutsideTheEnum($this->boot($environmentVariable), '"order"');
    }

    /** an unquoted name is folded to lower case by the server, so a quoted `"Status"` in the CHECK does not exist */
    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderPostgreSqlEngine')]
    public function testPostgresqlConstrainsAMixedCaseColumnName(string $environmentVariable): void
    {
        $this->assertTheServerRejectsAValueOutsideTheEnum($this->boot($environmentVariable), 'Status');
    }

    /** the inline CHECK travels into `ALTER TABLE ... ALTER col TYPE`, which PostgreSQL does not accept */
    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderPostgreSqlEngine')]
    public function testPostgresqlCannotAlterAPortableEnumColumn(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);
        IntegrationDatabase::registerTypes();

        $table = new Table(static::TABLE_NAME);
        $table->addColumn('status', TestPortableEnumType::getDefaultName(), ['length' => 32, 'notnull' => false]);
        IntegrationDatabase::createTable($connection, $table);

        $schemaManager = $connection->createSchemaManager();
        $tableDiff = $schemaManager->createComparator()->compareTables(
            $schemaManager->introspectTable(static::TABLE_NAME),
            $table,
        );
        $alterStatements = $connection->getDatabasePlatform()->getAlterTableSQL($tableDiff);

        static::assertNotSame([], $alterStatements);
        static::assertStringContainsString('CHECK', $alterStatements[0]);

        $this->expectException(DbalException::class);
        $connection->executeStatement($alterStatements[0]);
    }

    protected function tearDown(): void
    {
        if (null !== $this->connection) {
            IntegrationDatabase::dropTable($this->connection, static::TABLE_NAME);
            $this->connection->close();
            $this->connection = null;
        }

        AbstractPhpEnumType::clearCache();

        parent::tearDown();
    }

    private function boot(string $environmentVariable): Connection
    {
        try {
            $this->connection = IntegrationDatabase::createConnection($environmentVariable);
        } catch (SkipIntegrationException $skipIntegrationException) {
            static::markTestSkipped($skipIntegrationException->getMessage());
        }

        return $this->connection;
    }

    /** through `Schema::toSql()`, so the assertion covers the DDL a consumer actually gets, not a hand-built one */
    private function assertTheServerRejectsAValueOutsideTheEnum(Connection $connection, string $columnName = 'status'): void
    {
        IntegrationDatabase::registerTypes();

        $table = new Table(static::TABLE_NAME);
        $table->addColumn($columnName, TestPortableEnumType::getDefaultName(), ['length' => 32, 'notnull' => false]);
        IntegrationDatabase::createTable($connection, $table);

        $quotedTable = $connection->getDatabasePlatform()->quoteSingleIdentifier(static::TABLE_NAME);
        $connection->insert(static::TABLE_NAME, [$columnName => 'first_value']);

        static::assertSame(
            'first_value',
            $connection->fetchOne(\sprintf('SELECT %s FROM %s', $columnName, $quotedTable)),
        );

        try {
            $connection->insert(static::TABLE_NAME, [$columnName => 'outside_enum']);

            static::fail('the server accepted a value the CHECK constraint forbids');
        } catch (DbalException $dbalException) {
            static::assertNotSame('', $dbalException->getMessage());
        }
    }
}
