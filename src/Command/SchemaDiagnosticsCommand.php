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

    protected const USAGE = "usage: doctrine-type-diagnose <database-url>\n";

    /** @var array<int, string> */
    protected const HELP_FLAG_LIST = ['--help', '-h'];

    /**
     * the streams are injectable so a test can read what the command prints; a resource has no type declaration,
     * hence the `mixed` and the check in the accessors
     *
     * @param resource|null $standardOutput defaults to the process's standard output
     * @param resource|null $standardError defaults to the process's standard error
     */
    public function __construct(
        protected SchemaDiagnostics $diagnostics = new SchemaDiagnostics(),
        protected mixed $standardOutput = null,
        protected mixed $standardError = null,
    ) {}

    /** @param list<string> $arguments the process arguments, the first one being the script itself */
    public function run(array $arguments): int
    {
        $positionalArguments = \array_slice($arguments, 1);

        if ([] !== \array_intersect($positionalArguments, static::HELP_FLAG_LIST)) {
            \fwrite($this->getStandardOutput(), static::USAGE);

            return static::EXIT_SUCCESS;
        }

        $databaseUrl = $positionalArguments[0] ?? '';

        if ('' === $databaseUrl || 1 !== \count($positionalArguments)) {
            \fwrite($this->getStandardError(), static::USAGE);

            return static::EXIT_USAGE;
        }

        $connection = null;

        try {
            $connection = $this->createConnection($databaseUrl);

            if (false === $this->diagnostics->supports($connection)) {
                \fwrite(
                    $this->getStandardError(),
                    "note: this platform has no introspection query, nothing was inspected\n",
                );

                return static::EXIT_SUCCESS;
            }

            $diagnostics = $this->diagnostics->inspect($connection);

            foreach ($diagnostics as $diagnostic) {
                \fwrite($this->getStandardOutput(), \sprintf(
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
            \fwrite($this->getStandardError(), \sprintf("error: %s\n", $throwable->getMessage()));

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

    /** @return resource */
    protected function getStandardOutput()
    {
        return true === \is_resource($this->standardOutput) ? $this->standardOutput : \STDOUT;
    }

    /** @return resource */
    protected function getStandardError()
    {
        return true === \is_resource($this->standardError) ? $this->standardError : \STDERR;
    }
}
