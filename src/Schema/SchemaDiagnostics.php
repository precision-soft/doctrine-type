<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PrecisionSoft\Doctrine\Type\Exception\Exception;

class SchemaDiagnostics
{
    /**
     * @return list<Diagnostic>
     * @throws Exception if the connection names no database, because both queries filter on the current one
     * @throws DbalException if the schema cannot be read
     */
    public function inspect(Connection $connection): array
    {
        $diagnostics = [];

        foreach ($this->listDatabaseColumns($connection) as $databaseColumn) {
            $diagnostic = $this->diagnose(
                $databaseColumn['table'],
                $databaseColumn['column'],
                $databaseColumn['databaseType'],
                $databaseColumn['unsigned'],
            );

            if (null !== $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
        }

        return $diagnostics;
    }

    public function supports(Connection $connection): bool
    {
        $platform = $connection->getDatabasePlatform();

        return true === $platform instanceof AbstractMySQLPlatform || true === $platform instanceof PostgreSQLPlatform;
    }

    public function diagnose(string $table, string $column, string $databaseType, bool $unsigned = false): ?Diagnostic
    {
        return match (\strtolower($databaseType)) {
            'enum', 'set' => new Diagnostic(
                $table,
                $column,
                $databaseType,
                Diagnostic::SEVERITY_WARNING,
                'Do not map this database type globally; map only this application type with getMappedDatabaseTypes(), or use AbstractPortableEnumType for new constrained columns.',
            ),
            'tinyint' => new Diagnostic(
                $table,
                $column,
                $databaseType,
                Diagnostic::SEVERITY_WARNING,
                true === $unsigned
                    ? 'Use UnsignedTinyintType for conversion-time range enforcement; keep database type mapping column-specific.'
                    : 'Use SignedTinyintType for conversion-time range enforcement; keep database type mapping column-specific.',
            ),
            default => null,
        };
    }

    /**
     * @return list<array{table: string, column: string, databaseType: string, unsigned: bool}>
     * @throws Exception if the connection names no database
     * @throws DbalException if the schema cannot be read
     */
    protected function listDatabaseColumns(Connection $connection): array
    {
        if (false === $this->supports($connection)) {
            return [];
        }

        /* `DATABASE()` and `current_schema()` are null on such a connection: the query matches nothing and the schema looks clean */
        $databaseName = $connection->getParams()['dbname'] ?? null;

        if (false === \is_string($databaseName) || '' === $databaseName) {
            throw new Exception('the connection names no database, nothing was inspected');
        }

        return true === $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform
            ? $this->listMySqlColumns($connection)
            : $this->listPostgreSqlColumns($connection);
    }

    /**
     * @return list<array{table: string, column: string, databaseType: string, unsigned: bool}>
     * @throws DbalException if the schema cannot be read
     */
    protected function listMySqlColumns(Connection $connection): array
    {
        /* aliased because MySQL 8 returns `information_schema` headers in their canonical upper case whatever the query says, MariaDB as written */
        $rows = $connection->fetchAllAssociative(
            "SELECT tableColumn.table_name AS tableName, tableColumn.column_name AS columnName,
                 tableColumn.data_type AS dataType, tableColumn.column_type AS columnType
             FROM information_schema.columns AS tableColumn
             INNER JOIN information_schema.tables AS baseTable
                 ON baseTable.table_schema = tableColumn.table_schema
                 AND baseTable.table_name = tableColumn.table_name
                 AND baseTable.table_type = 'BASE TABLE'
             WHERE tableColumn.table_schema = DATABASE()
             ORDER BY tableColumn.table_name, tableColumn.ordinal_position",
        );

        $columns = [];

        foreach ($rows as $row) {
            $columns[] = [
                'table' => (string)$row['tableName'],
                'column' => (string)$row['columnName'],
                'databaseType' => \strtolower((string)$row['dataType']),
                'unsigned' => \str_contains(\strtolower((string)$row['columnType']), 'unsigned'),
            ];
        }

        return $columns;
    }

    /**
     * @return list<array{table: string, column: string, databaseType: string, unsigned: bool}>
     * @throws DbalException if the schema cannot be read
     */
    protected function listPostgreSqlColumns(Connection $connection): array
    {
        /*
         * `udt_name` carries the user-defined type name, so the enum kind comes from `pg_type` rather than the column;
         * `current_schemas(false)` is the whole `search_path`, where `current_schema()` would be its first entry only
         */
        $rows = $connection->fetchAllAssociative(
            "SELECT tableColumn.table_name, tableColumn.column_name
             FROM information_schema.columns AS tableColumn
             INNER JOIN pg_catalog.pg_type AS userType ON userType.typname = tableColumn.udt_name
             INNER JOIN pg_catalog.pg_namespace AS userTypeNamespace
                 ON userTypeNamespace.oid = userType.typnamespace
                 AND userTypeNamespace.nspname = tableColumn.udt_schema
             INNER JOIN information_schema.tables AS baseTable
                 ON baseTable.table_schema = tableColumn.table_schema
                 AND baseTable.table_name = tableColumn.table_name
                 AND baseTable.table_type = 'BASE TABLE'
             WHERE tableColumn.table_schema = ANY (current_schemas(FALSE)) AND userType.typtype = 'e'
             ORDER BY tableColumn.table_name, tableColumn.ordinal_position",
        );

        $columns = [];

        foreach ($rows as $row) {
            $columns[] = [
                'table' => (string)$row['table_name'],
                'column' => (string)$row['column_name'],
                'databaseType' => 'enum',
                'unsigned' => false,
            ];
        }

        return $columns;
    }
}
