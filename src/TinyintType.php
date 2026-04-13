<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PrecisionSoft\Doctrine\Type\Contract\AbstractType;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;

class TinyintType extends AbstractType
{
    public const TINYINT = 'tinyint';

    public static function getDefaultName(): string
    {
        return self::TINYINT;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if (false === $platform instanceof MySQLPlatform) {
            throw new InvalidTypeValueException(
                \sprintf('this type only supports mysql, got `%s`', \get_class($platform)),
            );
        }

        $unsigned = true === ($column['unsigned'] ?? false) ? ' UNSIGNED' : '';

        return 'TINYINT' . $unsigned;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if (null === $value) {
            return null;
        }

        if (true === \is_int($value)) {
            $this->validateRange($value);

            return $value;
        }

        if (
            true === \is_string($value)
            && 1 === \preg_match('/^-?\d+$/', $value)
        ) {
            $this->validateRange((int)$value);

            return (int)$value;
        }

        throw new InvalidTypeValueException(
            \sprintf(
                'expected integer and got `%s`',
                true === \is_object($value) ? \get_class($value) : \gettype($value),
            ),
        );
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if (null === $value) {
            return null;
        }

        if (true === \is_int($value)) {
            $this->validateRange($value);

            return $value;
        }

        if (true === \is_string($value) && 1 === \preg_match('/^-?\d+$/', $value)) {
            $this->validateRange((int)$value);

            return (int)$value;
        }

        throw new InvalidTypeValueException(
            \sprintf(
                'expected integer and got `%s`',
                true === \is_object($value) ? \get_class($value) : \gettype($value),
            ),
        );
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::INTEGER;
    }

    protected function validateRange(int $tinyintValue): void
    {
        if (-128 > $tinyintValue || 255 < $tinyintValue) {
            $this->throwOutOfRangeException($tinyintValue);
        }
    }

    protected function throwOutOfRangeException(int $value): never
    {
        throw new InvalidTypeValueException(
            \sprintf('value `%d` is out of TINYINT range (-128 to 255)', $value),
        );
    }
}
