<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Contract;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;

abstract class AbstractTinyintType extends AbstractType
{
    public const TINYINT = 'tinyint';

    abstract protected function isWithinRange(int $tinyintValue): bool;

    abstract protected function getRangeDescription(): string;

    public static function getDefaultName(): string
    {
        return static::TINYINT;
    }

    /**
     * @throws Exception if the platform is not MySQL
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if (false === $platform instanceof AbstractMySQLPlatform) {
            throw new Exception(
                \sprintf('this type only supports mysql, got `%s`', $platform::class),
            );
        }

        $unsigned = true === ($column['unsigned'] ?? false) ? ' UNSIGNED' : '';

        return 'TINYINT' . $unsigned;
    }

    /**
     * @throws InvalidTypeValueException if the value is not a valid integer or is out of range
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        return null === $value ? null : $this->parseIntValue($value);
    }

    /**
     * @throws InvalidTypeValueException if the value is not a valid integer or is out of range
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?int
    {
        return null === $value ? null : $this->parseIntValue($value);
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::INTEGER;
    }

    protected function validateRange(int $tinyintValue): void
    {
        if (false === $this->isWithinRange($tinyintValue)) {
            $this->throwOutOfRangeException($tinyintValue);
        }
    }

    protected function throwOutOfRangeException(int|string $value): never
    {
        throw new InvalidTypeValueException(
            \sprintf('value `%s` is out of TINYINT range (%s)', $value, $this->getRangeDescription()),
        );
    }

    protected function parseIntValue(mixed $value): int
    {
        if (true === \is_int($value)) {
            $this->validateRange($value);

            return $value;
        }

        if (
            true === \is_string($value)
            && 1 === \preg_match('/^[+-]?\d+$/', $value)
        ) {
            $intValue = (int)$value;

            /* the message carries the original string: `(int)"999999999999999999999"` collapses to `PHP_INT_MAX` */
            if (false === $this->isWithinRange($intValue)) {
                $this->throwOutOfRangeException($value);
            }

            return $intValue;
        }

        throw new InvalidTypeValueException(
            \sprintf(
                'expected integer and got `%s`',
                true === \is_object($value) ? \get_class($value) : \gettype($value),
            ),
        );
    }
}
