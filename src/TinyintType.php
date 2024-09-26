<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Types\Type;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;

class TinyintType extends Type
{
    public const TINYINT = 'tinyint';

    public function getName(): string
    {
        return self::TINYINT;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if (false === ($platform instanceof MySQLPlatform)) {
            throw new Exception('this type only support mysql');
        }

        $unsigned = ($column['unsigned'] ?? false) === true ? ' UNSIGNED' : '';

        if (true === isset($column['length']) && is_numeric($column['length'])) {
            $sqlDeclaration = sprintf('tinyint(%d)', $column['length']);
        } else {
            $sqlDeclaration = 'tinyint';
        }

        return $sqlDeclaration . $unsigned;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): int|string|null
    {
        if (null === $value) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            return $value;
        }

        throw new InvalidTypeValueException(
            sprintf('expected integer and got `%s`', is_object($value) ? get_class($value) : gettype($value)),
        );
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?int
    {
        return null === $value ? null : (int)$value;
    }

    public function getBindingType(): int
    {
        return ParameterType::INTEGER;
    }
}
