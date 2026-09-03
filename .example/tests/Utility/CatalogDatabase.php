<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Test\Utility;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Tools\DsnParser;
use PrecisionSoft\Doctrine\Type\Example\Exception\Exception;

/** @internal */
final class CatalogDatabase
{
    /** @return iterable<string, array{string}> */
    public static function dataProviderMySqlEngine(): iterable
    {
        yield 'mysql' => ['DATABASE_URL_MYSQL'];
        yield 'mariadb' => ['DATABASE_URL_MARIADB'];
    }

    /** @return iterable<string, array{string}> */
    public static function dataProviderPostgreSqlEngine(): iterable
    {
        yield 'postgresql' => ['DATABASE_URL_POSTGRESQL'];
    }

    /** @return iterable<string, array{string}> */
    public static function dataProviderEveryEngine(): iterable
    {
        yield from static::dataProviderMySqlEngine();
        yield from static::dataProviderPostgreSqlEngine();
    }

    /** @throws SkipException when the engine is not there, so the caller skips instead of failing for the wrong reason */
    public static function connect(string $environmentVariable): Connection
    {
        $connection = DriverManager::getConnection(
            (new DsnParser(['mysql' => 'pdo_mysql', 'mariadb' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))
                ->parse(static::readDatabaseUrl($environmentVariable)),
        );

        try {
            $connection->executeQuery('SELECT 1');
        } catch (DbalException $dbalException) {
            throw new SkipException(\sprintf(
                'cannot reach the database behind `%s` (%s) - start it with `./dc --profile db up -d`',
                $environmentVariable,
                $dbalException->getMessage(),
            ));
        }

        return $connection;
    }

    public static function connectSqlite(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    /** @throws SkipException when the variable is not set: the example expects the dev container from `.dev/docker/` */
    public static function readDatabaseUrl(string $environmentVariable): string
    {
        $databaseUrl = \getenv($environmentVariable);

        if (false === \is_string($databaseUrl) || '' === $databaseUrl) {
            throw new SkipException(\sprintf('`%s` is not set - this suite expects the dev container', $environmentVariable));
        }

        return $databaseUrl;
    }

    /** the example shares the `test` database with the library's own suites, so it is dropped table by table */
    public static function dropCatalog(Connection $connection): void
    {
        $platform = $connection->getDatabasePlatform();

        foreach (['product', 'category'] as $tableName) {
            try {
                $connection->executeStatement('DROP TABLE IF EXISTS ' . $platform->quoteSingleIdentifier($tableName));
            } catch (DbalException $dbalException) {
                throw new Exception(\sprintf('cannot drop `%s`: %s', $tableName, $dbalException->getMessage()));
            }
        }
    }
}
