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
    private function assertTheServerRejectsAValueOutsideTheEnum(Connection $connection): void
    {
        IntegrationDatabase::registerTypes();

        $table = new Table(static::TABLE_NAME);
        $table->addColumn('status', TestPortableEnumType::getDefaultName(), ['length' => 32, 'notnull' => false]);
        IntegrationDatabase::createTable($connection, $table);

        $quotedTable = $connection->getDatabasePlatform()->quoteSingleIdentifier(static::TABLE_NAME);
        $connection->insert(static::TABLE_NAME, ['status' => 'first_value']);

        static::assertSame('first_value', $connection->fetchOne('SELECT status FROM ' . $quotedTable));

        try {
            $connection->insert(static::TABLE_NAME, ['status' => 'outside_enum']);

            static::fail('the server accepted a value the CHECK constraint forbids');
        } catch (DbalException $dbalException) {
            static::assertNotSame('', $dbalException->getMessage());
        }
    }
}
