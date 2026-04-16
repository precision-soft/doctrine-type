<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Contract;

use BackedEnum;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use UnitEnum;

abstract class AbstractSetType extends AbstractPhpEnumType
{
    /**
     * @throws InvalidTypeValueException if the value is not a valid array of enum cases
     */
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

        $allowedEnumClass = $this->getEnumClass();

        $convertedValues = \array_map(
            function (mixed $enumCase) use ($allowedEnumClass): mixed {
                /** @info typed enum sets reject null elements explicitly — silent filtering would mask bugs where the consumer expected an enum case and produced null. Untyped sets (no configured enum class) still allow null for backward compatibility with fixtures that use null to skip optional elements */
                if (null === $enumCase && null !== $allowedEnumClass) {
                    throw new InvalidTypeValueException(
                        \sprintf(
                            'set type `%s` does not allow null elements for typed enum sets; filter nulls before passing or use a non-enum set type',
                            static::getDefaultName(),
                        ),
                    );
                }

                if (null !== $allowedEnumClass && false === $enumCase instanceof UnitEnum) {
                    throw new InvalidTypeValueException(
                        \sprintf(
                            'expected enum case of `%s` for type `%s`',
                            $allowedEnumClass,
                            static::getDefaultName(),
                        ),
                    );
                }

                if (null !== $allowedEnumClass && false === $enumCase instanceof $allowedEnumClass) {
                    throw new InvalidTypeValueException(
                        \sprintf(
                            'enum case `%s` does not belong to `%s` for type `%s`',
                            $enumCase::class,
                            $allowedEnumClass,
                            static::getDefaultName(),
                        ),
                    );
                }

                $databaseValue = $this->convertValueToDatabase($enumCase);

                if (null === $databaseValue) {
                    return null;
                }

                $stringValue = (string)$databaseValue;

                if (true === \str_contains($stringValue, ',')) {
                    throw new InvalidTypeValueException(
                        \sprintf('set value `%s` must not contain a comma', $stringValue),
                    );
                }

                return $databaseValue;
            },
            $value,
        );

        $filteredValues = \array_filter(
            $convertedValues,
            static fn(mixed $convertedValue): bool => null !== $convertedValue && '' !== $convertedValue,
        );

        /** @info array_unique() uses loose comparison by design: backed enum values are homogeneous, so loose comparison never produces false positives */
        $uniqueValues = \array_unique($filteredValues);

        /** @info an empty set is stored as NULL, not as an empty string */
        return 0 === \count($uniqueValues) ? null : \implode(',', $uniqueValues);
    }

    /**
     * @return array<int, UnitEnum|BackedEnum>|null
     * @throws InvalidTypeValueException if the database value is not a string or contains an invalid enum case
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if (null === $value || '' === $value) {
            return null;
        }

        /** @info pass through already-hydrated arrays (e.g. tests that round-trip PHP values, or virtual/computed columns that bypass raw DB serialization) */
        if (true === \is_array($value)) {
            return $value;
        }

        if (false === \is_string($value)) {
            throw new InvalidTypeValueException(
                \sprintf('expected string for set type `%s`', static::getDefaultName()),
            );
        }

        /** @info `trim()` is defensive: MySQL SET values are stored comma-joined without spaces, but manual edits or copy-pasted fixtures can introduce whitespace which we silently normalize */
        return \array_map(
            fn(mixed $databaseValue): mixed => $this->convertValueToPhp(\trim($databaseValue)),
            \explode(',', $value),
        );
    }

    /**
     * @throws Exception if no enum class is configured or the class does not exist
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $quotedSetValues = [];

        foreach ($this->getValues() as $enumCase) {
            $quotedSetValues[] = $platform->quoteStringLiteral(
                (string)$this->convertValueToDatabase($enumCase),
            );
        }

        if (true === $platform instanceof AbstractMySQLPlatform) {
            return 'SET(' . \implode(',', $quotedSetValues) . ')';
        }

        /** @info non-MySQL platforms need `length` and `name` defaults, otherwise `getStringTypeDeclarationSQL` may fail or produce invalid SQL */
        $column['length'] ??= 255;
        $column['name'] ??= '';

        return $platform->getStringTypeDeclarationSQL($column);
    }
}
