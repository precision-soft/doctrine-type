<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Contract;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;

abstract class AbstractSetType extends AbstractPhpEnumType
{
    public function convertToDatabaseValue(mixed $values, AbstractPlatform $platform): ?string
    {
        if (null === $values) {
            return null;
        }

        $converted = array_map(
            fn(mixed $value): mixed => $this->convertValueToDatabase($value),
            (array)$values,
        );

        return 0 === count($converted) ? null : implode(',', $converted);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        return null === $value || '' === $value
            ? null
            : array_map(
                fn(mixed $item): mixed => $this->convertValueToPhp($item),
                explode(',', $value),
            );
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $values = [];

        foreach ($this->getValues() as $value) {
            $values[] = $platform->quoteStringLiteral(
                $this->convertValueToDatabase($value),
            );
        }

        if (true === $platform instanceof MySQLPlatform) {
            return 'SET(' . implode(',', $values) . ')';
        }

        return $platform->getIntegerTypeDeclarationSQL($column);
    }
}
