<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Contract;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;

abstract class AbstractEnumType extends AbstractPhpEnumType
{
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if (null === $value) {
            return null;
        }

        return $this->convertValueToDatabase($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return null === $value ? null : $this->convertValueToPhp($value);
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $values = [];

        foreach ($this->getValues() as $value) {
            $values[] = $platform->quoteStringLiteral(
                $this->convertValueToDatabase($value),
            );
        }

        if (true === $platform instanceof MySQLPlatform) {
            return 'ENUM(' . implode(',', $values) . ')';
        }

        return $platform->getIntegerTypeDeclarationSQL($column);
    }
}
