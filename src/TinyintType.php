<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PrecisionSoft\Doctrine\Type\Contract\AbstractType;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;

class TinyintType extends AbstractType
{
    public const TINYINT = 'tinyint';

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
        /** @info the combined signed+unsigned range (-128..255) is intentional: Doctrine does not pass column metadata to convertToDatabaseValue, so both ranges must be accepted; SQL correctness is enforced separately via getSQLDeclaration */
        if (-128 > $tinyintValue || 255 < $tinyintValue) {
            $this->throwOutOfRangeException($tinyintValue);
        }
    }

    protected function throwOutOfRangeException(int|string $value): never
    {
        throw new InvalidTypeValueException(
            \sprintf('value `%s` is out of TINYINT range (-128 to 255)', $value),
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

            /** @info range check uses the original string so the error message reflects the input verbatim instead of a silently truncated `PHP_INT_MAX` (e.g. `(int)"999999999999999999999"` collapses to `PHP_INT_MAX` on 64-bit) */
            if (-128 > $intValue || 255 < $intValue) {
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
