<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PrecisionSoft\Doctrine\Type\Schema\SchemaDiagnostics;
use Throwable;

class SchemaDiagnosticsCommand
{
    public const EXIT_SUCCESS = 0;

    public const EXIT_DIAGNOSTICS_FOUND = 1;

    public const EXIT_USAGE = 2;

    public const EXIT_FAILURE = 3;

    /** @var array<string, string> */
    protected const DRIVER_SCHEME_MAP = [
        'mysql' => 'pdo_mysql',
        'mariadb' => 'pdo_mysql',
        'postgresql' => 'pdo_pgsql',
        'postgres' => 'pdo_pgsql',
        'sqlite' => 'pdo_sqlite',
    ];

    public function __construct(protected SchemaDiagnostics $diagnostics = new SchemaDiagnostics()) {}

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        $databaseUrl = $arguments[1] ?? '';

        if ('' === $databaseUrl) {
            \fwrite(\STDERR, "usage: doctrine-type-diagnose <database-url>\n");

            return static::EXIT_USAGE;
        }

        $connection = null;

        try {
            $connection = $this->createConnection($databaseUrl);

            if (false === $this->diagnostics->supports($connection)) {
                \fwrite(\STDERR, "note: this platform has no introspection query, nothing was inspected\n");

                return static::EXIT_SUCCESS;
            }

            $diagnostics = $this->diagnostics->inspect($connection);

            foreach ($diagnostics as $diagnostic) {
                \fwrite(\STDOUT, \sprintf(
                    "%s\t%s.%s\t%s\t%s\n",
                    $diagnostic->severity,
                    $diagnostic->table,
                    $diagnostic->column,
                    $diagnostic->databaseType,
                    $diagnostic->message,
                ));
            }

            return 0 === \count($diagnostics) ? static::EXIT_SUCCESS : static::EXIT_DIAGNOSTICS_FOUND;
        } catch (Throwable $throwable) {
            \fwrite(\STDERR, \sprintf("error: %s\n", $throwable->getMessage()));

            return static::EXIT_FAILURE;
        } finally {
            $connection?->close();
        }
    }

    protected function createConnection(string $databaseUrl): Connection
    {
        return DriverManager::getConnection(
            (new DsnParser(static::DRIVER_SCHEME_MAP))->parse($databaseUrl),
        );
    }
}
