<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Contract;

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
                /* a typed set rejects null loudly; an untyped one still accepts it, and that asymmetry is kept */
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

        /* loose comparison is safe here: one enum's backing values are homogeneous */
        $uniqueValues = \array_unique($filteredValues);

        return 0 === \count($uniqueValues) ? null : \implode(',', $uniqueValues);
    }

    /**
     * @return array<int, UnitEnum|string|int>|null raw values in `notEnum` mode, as in `AbstractPhpEnumType::getValues()`
     * @throws InvalidTypeValueException if the database value is not a string or contains an invalid enum case
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if (null === $value || '' === $value) {
            return null;
        }

        /* an already-hydrated array reaches here from a virtual column, and every element is still checked */
        if (true === \is_array($value)) {
            $allowedEnumClass = $this->getEnumClass();

            if (null !== $allowedEnumClass) {
                foreach ($value as $element) {
                    if (false === $element instanceof UnitEnum) {
                        throw new InvalidTypeValueException(
                            \sprintf(
                                'expected enum case of `%s` for type `%s`',
                                $allowedEnumClass,
                                static::getDefaultName(),
                            ),
                        );
                    }

                    if (false === $element instanceof $allowedEnumClass) {
                        throw new InvalidTypeValueException(
                            \sprintf(
                                'enum case `%s` does not belong to `%s` for type `%s`',
                                $element::class,
                                $allowedEnumClass,
                                static::getDefaultName(),
                            ),
                        );
                    }
                }
            }

            return $value;
        }

        if (false === \is_string($value)) {
            throw new InvalidTypeValueException(
                \sprintf('expected string for set type `%s`', static::getDefaultName()),
            );
        }

        /* the server never writes spaces around the commas, but a hand-edited column can carry them */

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
        return $this->buildSqlDeclaration('SET', $column, $platform);
    }
}
