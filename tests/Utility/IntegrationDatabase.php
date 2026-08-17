<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Utility;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use PrecisionSoft\Doctrine\Type\DateTimeType;
use PrecisionSoft\Doctrine\Type\TinyintType;
use RuntimeException;

/** @internal */
final class IntegrationDatabase
{
    public const TABLE_NAME = 'doctrine_type_probe';

    /** spelled out because `DateTimeType` extends DBAL's own, not `AbstractType`, so it has no `getDefaultName()` */
    public const DATE_TIME_TYPE_NAME = 'psDateTime';

    /** @return iterable<string, array{string}> */
    public static function dataProviderEngine(): iterable
    {
        yield 'mysql' => ['DATABASE_URL_MYSQL'];
        yield 'mariadb' => ['DATABASE_URL_MARIADB'];
    }

    /**
     * @throws SkipIntegrationException when the database is unreachable, so the caller can skip rather than fail
     */
    public static function createConnection(string $environmentVariable): Connection
    {
        $databaseUrl = \getenv($environmentVariable);

        if (false === $databaseUrl || '' === $databaseUrl) {
            throw new SkipIntegrationException(\sprintf(
                '`%s` is not set — this suite expects the dev container from `.dev/docker/`',
                $environmentVariable,
            ));
        }

        /* outside the try below, so only an unreachable server can become a skip */
        $connection = DriverManager::getConnection(
            (new DsnParser(['mysql' => 'pdo_mysql', 'mariadb' => 'pdo_mysql']))->parse($databaseUrl),
        );

        try {
            $connection->executeQuery('SELECT 1');
        } catch (DbalException $dbalException) {
            throw new SkipIntegrationException(\sprintf(
                'cannot reach the database behind `%s` (%s) — start it with `./dc --profile db up -d`',
                $environmentVariable,
                $dbalException->getMessage(),
            ));
        }

        return $connection;
    }

    /** guarded because the registry is global and the engine provider runs this once per row, in one process */
    public static function registerTypes(): void
    {
        $typeClasses = [
            TestBackedEnumType::getDefaultName() => TestBackedEnumType::class,
            TestIntBackedEnumType::getDefaultName() => TestIntBackedEnumType::class,
            TestSimpleEnumType::getDefaultName() => TestSimpleEnumType::class,
            TestBackedSetType::getDefaultName() => TestBackedSetType::class,
            TestIntBackedSetType::getDefaultName() => TestIntBackedSetType::class,
            TestSimpleSetType::getDefaultName() => TestSimpleSetType::class,
            TinyintType::getDefaultName() => TinyintType::class,
            static::DATE_TIME_TYPE_NAME => DateTimeType::class,
        ];

        foreach ($typeClasses as $typeName => $typeClass) {
            if (false === Type::hasType($typeName)) {
                Type::addType($typeName, $typeClass);
            }

            if (false === (Type::getType($typeName) instanceof $typeClass)) {
                throw new RuntimeException(\sprintf('`%s` is not registered as `%s`', $typeName, $typeClass));
            }
        }
    }

    /** drops first, so a table left by a crashed run cannot silently satisfy the next one */
    public static function createTable(Connection $connection, Table $table): void
    {
        static::dropTable($connection, static::TABLE_NAME);

        foreach ((new Schema([$table]))->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public static function dropTable(Connection $connection, string $tableName): void
    {
        $connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', $tableName));
    }
}
