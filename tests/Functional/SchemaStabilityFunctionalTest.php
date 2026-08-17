<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Test\Utility\IntegrationDatabase;
use PrecisionSoft\Doctrine\Type\Test\Utility\MappedEnumType;
use PrecisionSoft\Doctrine\Type\Test\Utility\SkipIntegrationException;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnumType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedSetType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestIntBackedEnumType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnumType;
use PrecisionSoft\Doctrine\Type\TinyintType;

/** @internal */
#[Group('integration')]
final class SchemaStabilityFunctionalTest extends TestCase
{
    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        if (null !== $this->connection) {
            IntegrationDatabase::dropTable($this->connection, IntegrationDatabase::TABLE_NAME);
            $this->connection->close();
            $this->connection = null;
        }

        parent::tearDown();
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderEngine')]
    public function testEnumColumnsNeverSettle(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);

        $enumTypeNames = [
            TestBackedEnumType::getDefaultName(),
            TestIntBackedEnumType::getDefaultName(),
            TestSimpleEnumType::getDefaultName(),
        ];

        foreach ($enumTypeNames as $enumTypeName) {
            $table = $this->createProbeTable($connection, 'enum_column', $enumTypeName);

            static::assertFalse(
                $this->compare($connection, $table)->isEmpty(),
                \sprintf('`%s` unexpectedly round-trips — the known limitation may be fixed', $enumTypeName),
            );
        }
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderEngine')]
    public function testSetColumnNeverSettles(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);

        $table = $this->createProbeTable($connection, 'set_column', TestBackedSetType::getDefaultName());

        static::assertFalse($this->compare($connection, $table)->isEmpty());
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderEngine')]
    public function testTheAlterStatementIsAByteIdenticalNoOp(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);

        $table = $this->createProbeTable($connection, 'enum_column', TestBackedEnumType::getDefaultName());
        $platform = $connection->getDatabasePlatform();
        $alterStatements = $platform->getAlterTableSQL($this->compare($connection, $table));

        static::assertCount(1, $alterStatements);
        static::assertStringContainsString(
            "ENUM('first_value','second_value','third_value')",
            $alterStatements[0],
        );
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderEngine')]
    public function testSignedTinyintSettlesAndUnsignedDoesNot(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);

        $signedTable = $this->createProbeTable($connection, 'tinyint_column', TinyintType::getDefaultName());

        static::assertTrue(
            $this->compare($connection, $signedTable)->isEmpty(),
            'a signed TINYINT column must round-trip',
        );

        $unsignedTable = $this->createProbeTable(
            $connection,
            'tinyint_column',
            TinyintType::getDefaultName(),
            ['unsigned' => true],
        );

        static::assertFalse($this->compare($connection, $unsignedTable)->isEmpty());
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderEngine')]
    public function testPlainDateTimeSettles(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);

        $table = $this->createProbeTable($connection, 'datetime_column', IntegrationDatabase::DATE_TIME_TYPE_NAME);

        static::assertTrue($this->compare($connection, $table)->isEmpty());
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderEngine')]
    public function testDateTimeWithOnUpdateNeverSettles(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);

        $table = $this->createProbeTable(
            $connection,
            'datetime_column',
            IntegrationDatabase::DATE_TIME_TYPE_NAME,
            [],
            ['update' => true],
        );

        static::assertFalse(
            $this->compare($connection, $table)->isEmpty(),
            'DBAL has started modelling ON UPDATE CURRENT_TIMESTAMP — the README limitation needs revisiting',
        );
    }

    /** separate process because the type map is global: registering this type invalidates every test above */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderEngine')]
    public function testDeclaringTheDatabaseTypeMakesTheSchemaSettle(string $environmentVariable): void
    {
        /* before the connection exists, so no platform can cache its type mappings first */
        if (false === Type::hasType(MappedEnumType::getDefaultName())) {
            Type::addType(MappedEnumType::getDefaultName(), MappedEnumType::class);
        }

        $connection = $this->boot($environmentVariable);

        $table = $this->createProbeTable($connection, 'enum_column', MappedEnumType::getDefaultName());

        static::assertTrue(
            $this->compare($connection, $table)->isEmpty(),
            'claiming the database type must make the round trip stable',
        );
    }

    private function boot(string $environmentVariable): Connection
    {
        try {
            $this->connection = IntegrationDatabase::createConnection($environmentVariable);
        } catch (SkipIntegrationException $skipIntegrationException) {
            static::markTestSkipped($skipIntegrationException->getMessage());
        }

        IntegrationDatabase::registerTypes();

        return $this->connection;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $platformOptions
     */
    private function createProbeTable(
        Connection $connection,
        string $columnName,
        string $typeName,
        array $options = [],
        array $platformOptions = [],
    ): Table {
        $table = new Table(IntegrationDatabase::TABLE_NAME);
        $column = $table->addColumn($columnName, $typeName, $options + ['notnull' => false]);

        if ([] !== $platformOptions) {
            $column->setPlatformOptions($platformOptions);
        }

        IntegrationDatabase::createTable($connection, $table);

        return $table;
    }

    private function compare(Connection $connection, Table $table): TableDiff
    {
        $schemaManager = $connection->createSchemaManager();

        return $schemaManager->createComparator()->compareTables(
            $schemaManager->introspectTable(IntegrationDatabase::TABLE_NAME),
            $table,
        );
    }
}
