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

        $allowedEnumClass = $this->getEnumClass();

        $convertedValues = \array_map(
            function (mixed $enumCase) use ($allowedEnumClass): mixed {
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

                if (null !== $databaseValue) {
                    $stringValue = (string)$databaseValue;

                    if (true === \str_contains($stringValue, ',')) {
                        throw new InvalidTypeValueException(
                            \sprintf('set value `%s` must not contain a comma', $stringValue),
                        );
                    }
                }

                return $databaseValue;
            },
            $value,
        );

        $filteredValues = \array_filter(
            $convertedValues,
            static fn(mixed $convertedValue): bool => null !== $convertedValue && '' !== $convertedValue,
        );

        $uniqueValues = \array_unique($filteredValues);

        return 0 === \count($uniqueValues) ? null : \implode(',', $uniqueValues);
    }

    /** @return array<int, UnitEnum|BackedEnum>|null */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (false === \is_string($value)) {
            throw new InvalidTypeValueException(
                \sprintf('expected string for set type `%s`', static::getDefaultName()),
            );
        }

        return \array_map(
            fn(mixed $databaseValue): mixed => $this->convertValueToPhp(\trim($databaseValue)),
            \explode(',', $value),
        );
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $quotedSetValues = [];

        foreach ($this->getValues() as $enumCase) {
            $quotedSetValues[] = $platform->quoteStringLiteral(
                (string)$this->convertValueToDatabase($enumCase),
            );
        }

        if (true === $platform instanceof MySQLPlatform) {
            return 'SET(' . \implode(',', $quotedSetValues) . ')';
        }

        return $platform->getStringTypeDeclarationSQL($column);
    }
}
