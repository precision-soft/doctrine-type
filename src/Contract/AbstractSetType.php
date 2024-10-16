<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Contract;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;

abstract class AbstractSetType extends AbstractPhpEnumType
{
    public function convertToDatabaseValue(mixed $values, AbstractPlatform $platform): ?string
    {
        if (null !== $values) {
            $values = (array)$values;

            $values = array_map(
                fn(mixed $value) => $this->convertValueToDatabase($value),
                $values
            );

            $values = true === empty($values) ? null : \implode(',', $values);
        }

        return (null === $values) ? null : $values;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        return true === empty($value)
            ? null
            : array_map(
                fn(mixed $value) => $this->convertValueToPhp($value),
                \explode(',', $value)
            );
    }

    public function getSqlDeclaration(array $column, AbstractPlatform $platform): string
    {
        $values = [];

        foreach ($this->getValues() as $value) {
            $values[] = $platform->quoteStringLiteral(
                $this->convertValueToDatabase($value)
            );
        }

        if ($platform instanceof MySqlPlatform) {
            return 'SET(' . \implode(',', $values) . ')';
        }

        return $platform->getIntegerTypeDeclarationSQL($column);
    }
}
