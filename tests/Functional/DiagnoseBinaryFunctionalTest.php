<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Command\SchemaDiagnosticsCommand;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Test\Utility\IntegrationDatabase;
use PrecisionSoft\Doctrine\Type\Test\Utility\SkipIntegrationException;

/**
 * runs `bin/doctrine-type-diagnose` as the process a consumer runs, against the real engines
 *
 * @internal
 */
#[Group('integration')]
final class DiagnoseBinaryFunctionalTest extends TestCase
{
    private const TABLE_NAME = 'diagnose_binary_probe';

    private const POSTGRESQL_ENUM_TYPE_NAME = 'diagnose_binary_status';

    private const USAGE = "usage: doctrine-type-diagnose <database-url>\n";

    private ?Connection $connection = null;

    /** @return iterable<string, array{string}> */
    public static function dataProviderEveryEngine(): iterable
    {
        yield from IntegrationDatabase::dataProviderMySqlEngine();
        yield from IntegrationDatabase::dataProviderPostgreSqlEngine();
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderMySqlEngine')]
    public function testEveryUnmappedMySqlColumnIsPrintedAsATabSeparatedLine(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);
        $connection->executeStatement(\sprintf(
            "CREATE TABLE %s (status ENUM('first_value','second_value'), flags TINYINT UNSIGNED, title VARCHAR(32))",
            static::TABLE_NAME,
        ));

        $result = $this->runBinary([$this->readDatabaseUrl($environmentVariable)]);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_DIAGNOSTICS_FOUND, $result['exitCode'], $result['standardError']);
        static::assertSame('', $result['standardError']);
        static::assertSame(
            [
                \sprintf("warning\t%s.status\tenum", static::TABLE_NAME),
                \sprintf("warning\t%s.flags\ttinyint", static::TABLE_NAME),
            ],
            $this->readColumnsOfProbeTable($result['standardOutput']),
        );
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderPostgreSqlEngine')]
    public function testAPostgreSqlEnumColumnIsPrinted(string $environmentVariable): void
    {
        $connection = $this->boot($environmentVariable);
        $connection->executeStatement(\sprintf(
            "CREATE type %s AS ENUM ('first_value','second_value')",
            static::POSTGRESQL_ENUM_TYPE_NAME,
        ));
        $connection->executeStatement(\sprintf(
            'CREATE TABLE %s (status %s, title VARCHAR(32))',
            static::TABLE_NAME,
            static::POSTGRESQL_ENUM_TYPE_NAME,
        ));

        $result = $this->runBinary([$this->readDatabaseUrl($environmentVariable)]);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_DIAGNOSTICS_FOUND, $result['exitCode'], $result['standardError']);
        static::assertSame('', $result['standardError']);
        static::assertSame(
            [\sprintf("warning\t%s.status\tenum", static::TABLE_NAME)],
            $this->readColumnsOfProbeTable($result['standardOutput']),
        );
    }

    #[DataProviderExternal(IntegrationDatabase::class, 'dataProviderMySqlEngine')]
    public function testAUrlWithoutADatabaseIsAFailureNotACleanSchema(string $environmentVariable): void
    {
        $this->boot($environmentVariable);
        $databaseUrl = $this->readDatabaseUrl($environmentVariable);
        $urlWithoutDatabase = (string)\preg_replace('#/[^/?]+(\?|$)#', '$1', $databaseUrl, 1);

        static::assertNotSame($databaseUrl, $urlWithoutDatabase);

        $result = $this->runBinary([$urlWithoutDatabase]);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_FAILURE, $result['exitCode']);
        static::assertSame('', $result['standardOutput']);
        static::assertStringContainsString('error: the connection names no database', $result['standardError']);
    }

    public function testNoArgumentPrintsTheUsageOnStandardError(): void
    {
        $result = $this->runBinary([]);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_USAGE, $result['exitCode']);
        static::assertSame('', $result['standardOutput']);
        static::assertSame(static::USAGE, $result['standardError']);
    }

    public function testHelpPrintsTheUsageOnStandardOutput(): void
    {
        $result = $this->runBinary(['--help']);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_SUCCESS, $result['exitCode']);
        static::assertSame(static::USAGE, $result['standardOutput']);
        static::assertSame('', $result['standardError']);
    }

    public function testAnUnreachableServerIsAFailure(): void
    {
        $result = $this->runBinary(['mysql://nobody:x@127.0.0.1:1/none']);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_FAILURE, $result['exitCode']);
        static::assertSame('', $result['standardOutput']);
        static::assertStringStartsWith('error: ', $result['standardError']);
    }

    protected function tearDown(): void
    {
        if (null !== $this->connection) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS ' . static::TABLE_NAME);

            if (true === $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $this->connection->executeStatement('DROP type IF EXISTS ' . static::POSTGRESQL_ENUM_TYPE_NAME);
            }

            $this->connection->close();
            $this->connection = null;
        }

        parent::tearDown();
    }

    private function boot(string $environmentVariable): Connection
    {
        try {
            $this->connection = IntegrationDatabase::createConnection($environmentVariable);
        } catch (SkipIntegrationException $skipIntegrationException) {
            static::markTestSkipped($skipIntegrationException->getMessage());
        }

        $this->connection->executeStatement('DROP TABLE IF EXISTS ' . static::TABLE_NAME);

        if (true === $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->connection->executeStatement('DROP type IF EXISTS ' . static::POSTGRESQL_ENUM_TYPE_NAME);
        }

        return $this->connection;
    }

    private function readDatabaseUrl(string $environmentVariable): string
    {
        $databaseUrl = \getenv($environmentVariable);

        if (false === \is_string($databaseUrl) || '' === $databaseUrl) {
            throw new Exception(\sprintf('`%s` is not set', $environmentVariable));
        }

        return $databaseUrl;
    }

    /**
     * @param list<string> $arguments
     * @return array{exitCode: int, standardOutput: string, standardError: string}
     */
    private function runBinary(array $arguments): array
    {
        $command = [\PHP_BINARY, \dirname(__DIR__, 2) . '/bin/doctrine-type-diagnose', ...$arguments];
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
     * the shared `test` database carries tables of the other suites too, so only the probe's rows are compared
     *
     * @return list<string>
     */
    private function readColumnsOfProbeTable(string $standardOutput): array
    {
        $lines = [];

        foreach (\explode("\n", \rtrim($standardOutput, "\n")) as $line) {
            $fields = \explode("\t", $line);

            if (true === \str_starts_with($fields[1] ?? '', static::TABLE_NAME . '.')) {
                $lines[] = \implode("\t", \array_slice($fields, 0, 3));
            }
        }

        return $lines;
    }
}
