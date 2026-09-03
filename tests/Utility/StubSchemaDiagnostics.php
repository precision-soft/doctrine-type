<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Utility;

use Doctrine\DBAL\Connection;
use PrecisionSoft\Doctrine\Type\Schema\Diagnostic;
use PrecisionSoft\Doctrine\Type\Schema\SchemaDiagnostics;
use Throwable;

/** @internal */
final class StubSchemaDiagnostics extends SchemaDiagnostics
{
    /** @param list<Diagnostic> $diagnostics */
    public function __construct(
        private readonly bool $supported = true,
        private readonly array $diagnostics = [],
        private readonly ?Throwable $throwable = null,
    ) {}

    public function inspect(Connection $connection): array
    {
        if (null !== $this->throwable) {
            throw $this->throwable;
        }

        return $this->diagnostics;
    }

    public function supports(Connection $connection): bool
    {
        return $this->supported;
    }
}
