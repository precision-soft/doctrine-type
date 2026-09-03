<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Utility;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PrecisionSoft\Doctrine\Type\Command\SchemaDiagnosticsCommand;

/** keeps the unit tests off the network: every url resolves to an in-memory sqlite connection */
final class TestSchemaDiagnosticsCommand extends SchemaDiagnosticsCommand
{
    /** @var list<string> */
    public array $receivedDatabaseUrls = [];

    public ?Connection $lastConnection = null;

    protected function createConnection(string $databaseUrl): Connection
    {
        $this->receivedDatabaseUrls[] = $databaseUrl;
        $this->lastConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->lastConnection->executeQuery('SELECT 1');

        return $this->lastConnection;
    }
}
