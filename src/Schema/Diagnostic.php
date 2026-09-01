<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Schema;

readonly class Diagnostic
{
    public const SEVERITY_WARNING = 'warning';

    public function __construct(
        public string $table,
        public string $column,
        public string $databaseType,
        public string $severity,
        public string $message,
    ) {}
}
