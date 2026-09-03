<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Service;

use Doctrine\DBAL\Connection;
use PrecisionSoft\Doctrine\Type\Example\Schema\CatalogSchema;
use PrecisionSoft\Doctrine\Type\Schema\SchemaDiagnostics;

/** what `doctrine:schema:update` would keep asking for, and what `doctrine-type-diagnose` says about it */
class SchemaReport
{
    public function __construct(
        protected Connection $connection,
        protected CatalogSchema $catalogSchema = new CatalogSchema(),
        protected SchemaDiagnostics $schemaDiagnostics = new SchemaDiagnostics(),
    ) {}

    /**
     * the columns whose desired declaration never equals the introspected one, as `table.column`
     *
     * @return list<string>
     */
    public function listColumnsThatNeverSettle(): array
    {
        $platform = $this->connection->getDatabasePlatform();
        $schemaManager = $this->connection->createSchemaManager();
        $comparator = $schemaManager->createComparator();
        $columnNames = [];

        foreach ($this->catalogSchema->build($platform)->getTables() as $table) {
            $tableDiff = $comparator->compareTables($schemaManager->introspectTable($table->getName()), $table);

            foreach ($tableDiff->getChangedColumns() as $columnDiff) {
                $columnNames[] = $table->getName() . '.' . $columnDiff->getNewColumn()->getName();
            }
        }

        \sort($columnNames);

        return $columnNames;
    }

    /**
     * the catalogue's rows of `doctrine-type-diagnose`, as `table.column` => database type
     *
     * @return array<string, string>
     */
    public function listDiagnostics(): array
    {
        if (false === $this->schemaDiagnostics->supports($this->connection)) {
            return [];
        }

        $catalogTables = [CatalogSchema::CATEGORY_TABLE, CatalogSchema::PRODUCT_TABLE];
        $diagnostics = [];

        foreach ($this->schemaDiagnostics->inspect($this->connection) as $diagnostic) {
            if (true === \in_array($diagnostic->table, $catalogTables, true)) {
                $diagnostics[$diagnostic->table . '.' . $diagnostic->column] = $diagnostic->databaseType;
            }
        }

        \ksort($diagnostics);

        return $diagnostics;
    }
}
