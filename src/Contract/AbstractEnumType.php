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

abstract class AbstractEnumType extends AbstractPhpEnumType
{
    /**
     * @throws InvalidTypeValueException if the value is not the expected enum type
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if (null === $value) {
            return null;
        }

        $enumClass = $this->getEnumClass();

        if (null !== $enumClass && true === $value instanceof UnitEnum && false === $value instanceof $enumClass) {
            throw new InvalidTypeValueException(
                \sprintf(
                    'enum case `%s` does not belong to `%s` for type `%s`',
                    $value::class,
                    $enumClass,
                    static::getDefaultName(),
                ),
            );
        }

        return $this->convertValueToDatabase($value);
    }

    /**
     * @throws InvalidTypeValueException if the database value does not match any enum case
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if (null === $value) {
            return null;
        }

        /** @info pass through already-hydrated enum cases (e.g. tests that round-trip PHP values, or virtual/computed columns that bypass raw DB serialization) */
        if (true === $value instanceof UnitEnum) {
            return $value;
        }

        return $this->convertValueToPhp($value);
    }

    /**
     * @throws Exception if no enum class is configured or the class does not exist
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $this->buildSqlDeclaration('ENUM', $column, $platform);
    }
}
