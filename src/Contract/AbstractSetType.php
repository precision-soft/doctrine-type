<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Contract;

use BackedEnum;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use UnitEnum;

abstract class AbstractSetType extends AbstractPhpEnumType
{
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (false === \is_array($value)) {
            throw new InvalidTypeValueException(
                \sprintf('expected array for set type `%s`', static::getDefaultName()),
            );
        }

        $convertedValues = \array_map(
            function (mixed $enumCase): mixed {
                $databaseValue = $this->convertValueToDatabase($enumCase);

                if (true === \is_string($databaseValue) && true === \str_contains($databaseValue, ',')) {
                    throw new InvalidTypeValueException(
                        \sprintf('set value `%s` must not contain a comma', $databaseValue),
                    );
                }

                return $databaseValue;
            },
            $value,
        );

        $filteredValues = \array_filter(
            $convertedValues,
            static fn(mixed $convertedValue): bool => null !== $convertedValue,
        );

        $uniqueValues = \array_unique($filteredValues);

        return 0 === \count($uniqueValues) ? null : \implode(',', $uniqueValues);
    }

    /** @return array<int, UnitEnum|BackedEnum>|null */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        return null === $value || '' === $value
            ? null
            : \array_map(
                fn(mixed $databaseValue): mixed => $this->convertValueToPhp(\trim($databaseValue)),
                \explode(',', $value),
            );
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $quotedSetValues = [];

        foreach ($this->getValues() as $enumCase) {
            $quotedSetValues[] = $platform->quoteStringLiteral(
                $this->convertValueToDatabase($enumCase),
            );
        }

        if (true === $platform instanceof MySQLPlatform) {
            return 'SET(' . \implode(',', $quotedSetValues) . ')';
        }

        return $platform->getStringTypeDeclarationSQL($column);
    }
}
