<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Test\Functional;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Command\SchemaDiagnosticsCommand;
use PrecisionSoft\Doctrine\Type\Example\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Example\Schema\CatalogSchema;
use PrecisionSoft\Doctrine\Type\Example\Service\CatalogRepository;
use PrecisionSoft\Doctrine\Type\Example\Test\Utility\CatalogDatabase;
use PrecisionSoft\Doctrine\Type\Example\Test\Utility\SkipException;

/**
 * `vendor/bin/doctrine-type-diagnose` run as the process a consumer runs, against the catalogue
 *
 * @internal
 */
final class DiagnoseBinaryTest extends TestCase
{
    private const USAGE = "usage: doctrine-type-diagnose <database-url>\n";

    private ?Connection $connection = null;

    #[DataProviderExternal(CatalogDatabase::class, 'dataProviderMySqlEngine')]
    public function testOnMysqlTheCatalogueGetsFiveRows(string $environmentVariable): void
    {
        $this->createCatalog($this->boot($environmentVariable));

        $result = $this->runBinary([CatalogDatabase::readDatabaseUrl($environmentVariable)]);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_DIAGNOSTICS_FOUND, $result['exitCode'], $result['standardError']);
        static::assertSame('', $result['standardError']);
        static::assertSame(
            [
                "warning\tcategory.order\ttinyint",
                "warning\tproduct.status\tenum",
                "warning\tproduct.currency\tenum",
                "warning\tproduct.channels\tset",
                "warning\tproduct.priority\ttinyint",
            ],
            $this->readCatalogueRows($result['standardOutput']),
        );
    }

    #[DataProviderExternal(CatalogDatabase::class, 'dataProviderPostgreSqlEngine')]
    public function testOnPostgresqlTheCatalogueIsClean(string $environmentVariable): void
    {
        $this->createCatalog($this->boot($environmentVariable));

        $result = $this->runBinary([CatalogDatabase::readDatabaseUrl($environmentVariable)]);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_SUCCESS, $result['exitCode'], $result['standardError'] . $result['standardOutput']);
        static::assertSame([], $this->readCatalogueRows($result['standardOutput']));
    }

    public function testHelpPrintsTheUsage(): void
    {
        $result = $this->runBinary(['--help']);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_SUCCESS, $result['exitCode']);
        static::assertSame(static::USAGE, $result['standardOutput']);
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

    private function createCatalog(Connection $connection): void
    {
        CatalogSchema::registerTypes();
        (new CatalogRepository($connection))->createSchema();
    }

    /**
     * @param list<string> $arguments
     * @return array{exitCode: int, standardOutput: string, standardError: string}
     */
    private function runBinary(array $arguments): array
    {
        $command = [\PHP_BINARY, \dirname(__DIR__, 2) . '/vendor/bin/doctrine-type-diagnose', ...$arguments];
        $pipes = [];
        $process = \proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (false === \is_resource($process)) {
            throw new Exception('cannot start the binary');
        }

        $standardOutput = (string)\stream_get_contents($pipes[1]);
        $standardError = (string)\stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);

        return [
            'exitCode' => \proc_close($process),
            'standardOutput' => $standardOutput,
            'standardError' => $standardError,
        ];
    }

    /**
     * the `test` database is shared with the library's own suites, so only the catalogue's rows are read
     *
     * @return list<string>
     */
    private function readCatalogueRows(string $standardOutput): array
    {
        $rows = [];

        foreach (\explode("\n", \rtrim($standardOutput, "\n")) as $line) {
            $fields = \explode("\t", $line);
            $table = \explode('.', $fields[1] ?? '')[0];

            if (true === \in_array($table, [CatalogSchema::CATEGORY_TABLE, CatalogSchema::PRODUCT_TABLE], true)) {
                $rows[] = \implode("\t", \array_slice($fields, 0, 3));
            }
        }

        return $rows;
    }
}
