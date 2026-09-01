<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Contract;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PrecisionSoft\Doctrine\Type\Exception\Exception;

abstract class AbstractPortableEnumType extends AbstractEnumType
{
    /**
     * @param array<int, string> $quotedValues
     * @param array<string, mixed> $column
     * @throws Exception if the column name is missing, so the constraint cannot target the wrong column
     */
    protected function decorateSqlDeclaration(
        string $declaration,
        array $quotedValues,
        array $column,
        AbstractPlatform $platform,
    ): string {
        if (false === $this->supportsInlineCheckConstraint($platform)) {
            return $declaration;
        }

        $columnName = $column['name'] ?? null;

        if (false === \is_string($columnName) || '' === $columnName) {
            throw new Exception('portable enum declarations require the column `name`');
        }

        return \sprintf(
            '%s CHECK (%s IN (%s))',
            $declaration,
            $platform->quoteSingleIdentifier($columnName),
            \implode(',', $quotedValues),
        );
    }

    protected function supportsInlineCheckConstraint(AbstractPlatform $platform): bool
    {
        return true === $platform instanceof PostgreSQLPlatform || true === $platform instanceof SQLitePlatform;
    }
}
