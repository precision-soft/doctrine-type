<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Contract;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;

abstract class AbstractSetType extends AbstractType
{
    abstract public function getValues(): array;

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null !== $value) {
            $value = (array) $value;

            if (false === empty($diff = \array_diff($value, $this->getValues()))) {
                throw new InvalidTypeValueException(
                    \sprintf(
                        'invalid value `%s`, expected one of `%s`, for `%s`',
                        \implode(', ', $diff),
                        \implode(', ', $this->getValues()),
                        $this->getName(),
                    ),
                );
            }

            $value = true === empty($value) ? null : \implode(',', $value);
        }

        return (null === $value) ? null : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        return true === empty($value) ? null : \explode(',', $value);
    }

    public function getSqlDeclaration(array $column, AbstractPlatform $platform): string
    {
        $values = [];

        foreach ($this->getValues() as $value) {
            $values[] = $platform->quoteStringLiteral($value);
        }

        if ($platform instanceof MySqlPlatform) {
            return 'SET(' . \implode(',', $values) . ')';
        }

        return $platform->getIntegerTypeDeclarationSQL($column);
    }
}
