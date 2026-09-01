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

class SchemaDiagnostics
{
    /**
     * @return list<Diagnostic>
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
     * @throws DbalException if the schema cannot be read
     */
    protected function listDatabaseColumns(Connection $connection): array
    {
        if (false === $this->supports($connection)) {
            return [];
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
        $rows = $connection->fetchAllAssociative(
            "SELECT tableColumn.TABLE_NAME, tableColumn.COLUMN_NAME, tableColumn.DATA_TYPE, tableColumn.COLUMN_TYPE
             FROM information_schema.COLUMNS AS tableColumn
             INNER JOIN information_schema.TABLES AS baseTable
                 ON baseTable.TABLE_SCHEMA = tableColumn.TABLE_SCHEMA
                 AND baseTable.TABLE_NAME = tableColumn.TABLE_NAME
                 AND baseTable.TABLE_TYPE = 'BASE TABLE'
             WHERE tableColumn.TABLE_SCHEMA = DATABASE()
             ORDER BY tableColumn.TABLE_NAME, tableColumn.ORDINAL_POSITION",
        );

        $columns = [];

        foreach ($rows as $row) {
            $columns[] = [
                'table' => (string)$row['TABLE_NAME'],
                'column' => (string)$row['COLUMN_NAME'],
                'databaseType' => \strtolower((string)$row['DATA_TYPE']),
                'unsigned' => \str_contains(\strtolower((string)$row['COLUMN_TYPE']), 'unsigned'),
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
        /* `udt_name` carries the user-defined type name, so the enum kind comes from `pg_type` rather than the column */
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
             WHERE tableColumn.table_schema = current_schema() AND userType.typtype = 'e'
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
