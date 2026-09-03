<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Test\Functional;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Example\Schema\CatalogSchema;
use PrecisionSoft\Doctrine\Type\Example\Service\CatalogRepository;
use PrecisionSoft\Doctrine\Type\Example\Service\SchemaReport;
use PrecisionSoft\Doctrine\Type\Example\Test\Utility\CatalogDatabase;
use PrecisionSoft\Doctrine\Type\Example\Test\Utility\SkipException;

/**
 * the schema churn the README's *Schema Stability* table announces, measured on the catalogue - expected output, not failures
 *
 * @internal
 */
final class SchemaReportTest extends TestCase
{
    private ?Connection $connection = null;

    #[DataProviderExternal(CatalogDatabase::class, 'dataProviderMySqlEngine')]
    public function testOnMysqlEveryColumnButTheSignedTinyintKeepsAskingForAnAlter(string $environmentVariable): void
    {
        $schemaReport = $this->createCatalogAndReport($this->boot($environmentVariable));

        static::assertSame(
            ['category.order', 'product.channels', 'product.currency', 'product.modified', 'product.status'],
            $schemaReport->listColumnsThatNeverSettle(),
        );
        static::assertSame(
            [
                'category.order' => 'tinyint',
                'product.channels' => 'set',
                'product.currency' => 'enum',
                'product.priority' => 'tinyint',
                'product.status' => 'enum',
            ],
            $schemaReport->listDiagnostics(),
        );
    }

    /** off MySQL only the portable enum churns (its CHECK is part of the declaration) and there is nothing to diagnose */
    #[DataProviderExternal(CatalogDatabase::class, 'dataProviderPostgreSqlEngine')]
    public function testOnPostgresqlOnlyThePortableEnumKeepsAskingForAnAlter(string $environmentVariable): void
    {
        $schemaReport = $this->createCatalogAndReport($this->boot($environmentVariable));

        static::assertSame(['product.status'], $schemaReport->listColumnsThatNeverSettle());
        static::assertSame([], $schemaReport->listDiagnostics());
    }

    protected function tearDown(): void
    {
        if (null !== $this->connection) {
            CatalogDatabase::dropCatalog($this->connection);
            $this->connection->close();
            $this->connection = null;
        }

        parent::tearDown();
    }

    private function boot(string $environmentVariable): Connection
    {
        try {
            $this->connection = CatalogDatabase::connect($environmentVariable);
        } catch (SkipException $skipException) {
            static::markTestSkipped($skipException->getMessage());
        }

        return $this->connection;
    }

    private function createCatalogAndReport(Connection $connection): SchemaReport
    {
        CatalogSchema::registerTypes();
        (new CatalogRepository($connection))->createSchema();

        return new SchemaReport($connection);
    }
}
