<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Functional;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Test\Utility\IntegrationDatabase;
use PrecisionSoft\Doctrine\Type\Test\Utility\SkipIntegrationException;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedEnumType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestBackedSetType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestIntBackedEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestIntBackedEnumType;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnum;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSimpleEnumType;
use PrecisionSoft\Doctrine\Type\TinyintType;

/** @internal */
#[Group('integration')]
final class ValueRoundTripFunctionalTest extends TestCase
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

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderMySqlEngine')]
    public function testBackedEnumRoundTripsThroughTheServer(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);
        $typeName = TestBackedEnumType::getDefaultName();

        $this->createProbeTable($connection, 'value_column', $typeName);
        $connection->insert(
            IntegrationDatabase::TABLE_NAME,
            ['value_column' => TestBackedEnum::second],
            ['value_column' => $typeName],
        );

        static::assertSame(
            'second_value',
            $connection->fetchOne(\sprintf('SELECT value_column FROM %s', IntegrationDatabase::TABLE_NAME)),
            'the column must hold the backing value, not the case name',
        );

        static::assertSame(TestBackedEnum::second, $this->readBack($connection, $typeName));
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderMySqlEngine')]
    public function testIntBackedEnumRoundTripsThroughTheServer(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);
        $typeName = TestIntBackedEnumType::getDefaultName();

        $this->createProbeTable($connection, 'value_column', $typeName);
        $connection->insert(
            IntegrationDatabase::TABLE_NAME,
            ['value_column' => TestIntBackedEnum::medium],
            ['value_column' => $typeName],
        );

        static::assertSame('5', $connection->fetchOne(\sprintf('SELECT value_column FROM %s', IntegrationDatabase::TABLE_NAME)));
        static::assertSame(TestIntBackedEnum::medium, $this->readBack($connection, $typeName));
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderMySqlEngine')]
    public function testPureEnumRoundTripsByCaseName(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);
        $typeName = TestSimpleEnumType::getDefaultName();

        $this->createProbeTable($connection, 'value_column', $typeName);
        $connection->insert(
            IntegrationDatabase::TABLE_NAME,
            ['value_column' => TestSimpleEnum::beta],
            ['value_column' => $typeName],
        );

        static::assertSame('beta', $connection->fetchOne(\sprintf('SELECT value_column FROM %s', IntegrationDatabase::TABLE_NAME)));
        static::assertSame(TestSimpleEnum::beta, $this->readBack($connection, $typeName));
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderMySqlEngine')]
    public function testTheServerRejectsAValueOutsideTheEnum(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);

        $this->createProbeTable($connection, 'value_column', TestBackedEnumType::getDefaultName());

        /* asserted on the reason, not the class: a non-strict server truncates instead of raising */
        $this->expectException(DbalException::class);
        $this->expectExceptionMessageMatches('/Data truncated/');

        $connection->executeStatement(
            \sprintf("INSERT INTO %s (value_column) VALUES ('not_a_case')", IntegrationDatabase::TABLE_NAME),
        );
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderMySqlEngine')]
    public function testSetIsNormalisedIntoDeclarationOrderByTheServer(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);
        $typeName = TestBackedSetType::getDefaultName();

        $this->createProbeTable($connection, 'value_column', $typeName);
        $connection->insert(
            IntegrationDatabase::TABLE_NAME,
            ['value_column' => [TestBackedEnum::third, TestBackedEnum::first]],
            ['value_column' => $typeName],
        );

        static::assertSame(
            'first_value,third_value',
            $connection->fetchOne(\sprintf('SELECT value_column FROM %s', IntegrationDatabase::TABLE_NAME)),
        );

        static::assertSame([TestBackedEnum::first, TestBackedEnum::third], $this->readBack($connection, $typeName));
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderMySqlEngine')]
    public function testEmptySetIsStoredAsNull(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);
        $typeName = TestBackedSetType::getDefaultName();

        $this->createProbeTable($connection, 'value_column', $typeName);
        $connection->insert(
            IntegrationDatabase::TABLE_NAME,
            ['value_column' => []],
            ['value_column' => $typeName],
        );

        static::assertNull($connection->fetchOne(\sprintf('SELECT value_column FROM %s', IntegrationDatabase::TABLE_NAME)));
        static::assertNull($this->readBack($connection, $typeName));
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderMySqlEngine')]
    public function testTinyintBoundariesRoundTrip(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);
        $typeName = TinyintType::getDefaultName();

        foreach ([-128, 0, 127] as $boundaryValue) {
            $this->createProbeTable($connection, 'value_column', $typeName);
            $connection->insert(
                IntegrationDatabase::TABLE_NAME,
                ['value_column' => $boundaryValue],
                ['value_column' => $typeName],
            );

            static::assertSame($boundaryValue, $this->readBack($connection, $typeName));
        }

        $this->createProbeTable($connection, 'value_column', $typeName, ['unsigned' => true]);
        $connection->insert(
            IntegrationDatabase::TABLE_NAME,
            ['value_column' => 255],
            ['value_column' => $typeName],
        );

        static::assertSame(255, $this->readBack($connection, $typeName));
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderMySqlEngine')]
    public function testAValueValidForUnsignedIsRejectedByASignedColumn(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);
        $typeName = TinyintType::getDefaultName();

        $this->createProbeTable($connection, 'value_column', $typeName);

        static::assertSame(
            200,
            Type::getType($typeName)->convertToDatabaseValue(200, $connection->getDatabasePlatform()),
            'the type accepts it — the range it validates is the union of signed and unsigned',
        );

        $this->expectException(DbalException::class);
        $this->expectExceptionMessageMatches('/Out of range/');

        $connection->insert(
            IntegrationDatabase::TABLE_NAME,
            ['value_column' => 200],
            ['value_column' => $typeName],
        );
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderMySqlEngine')]
    public function testOnUpdateCurrentTimestampFiresOnTheServer(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);

        $table = new Table(IntegrationDatabase::TABLE_NAME);
        $table->addColumn('id', 'integer', ['notnull' => true]);
        $touchedColumn = $table->addColumn(
            'touched',
            IntegrationDatabase::DATE_TIME_TYPE_NAME,
            ['notnull' => true],
        );
        $touchedColumn->setPlatformOptions(['update' => true]);

        IntegrationDatabase::createTable($connection, $table);

        $connection->insert(
            IntegrationDatabase::TABLE_NAME,
            ['id' => 1, 'touched' => new DateTime('2020-01-01 00:00:00')],
            ['id' => 'integer', 'touched' => IntegrationDatabase::DATE_TIME_TYPE_NAME],
        );

        $storedOnInsert = $connection->fetchOne(\sprintf('SELECT touched FROM %s WHERE id = 1', IntegrationDatabase::TABLE_NAME));

        static::assertSame('2020-01-01 00:00:00', $storedOnInsert, 'the insert must not be touched');

        $connection->executeStatement(\sprintf('UPDATE %s SET id = 2 WHERE id = 1', IntegrationDatabase::TABLE_NAME));

        static::assertNotSame(
            $storedOnInsert,
            $connection->fetchOne(\sprintf('SELECT touched FROM %s WHERE id = 2', IntegrationDatabase::TABLE_NAME)),
            'ON UPDATE CURRENT_TIMESTAMP must have rewritten the column',
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

    /** @param array<string, mixed> $options */
    private function createProbeTable(
        Connection $connection,
        string $columnName,
        string $typeName,
        array $options = [],
    ): void {
        $table = new Table(IntegrationDatabase::TABLE_NAME);
        $table->addColumn($columnName, $typeName, $options + ['notnull' => false]);

        IntegrationDatabase::createTable($connection, $table);
    }

    private function readBack(Connection $connection, string $typeName): mixed
    {
        return Type::getType($typeName)->convertToPHPValue(
            $connection->fetchOne(\sprintf('SELECT value_column FROM %s', IntegrationDatabase::TABLE_NAME)),
            $connection->getDatabasePlatform(),
        );
    }
}
