<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Contract;

use BackedEnum;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PrecisionSoft\Doctrine\Type\Enum\EnumType;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use ReflectionEnum;
use UnitEnum;

abstract class AbstractPhpEnumType extends AbstractType
{
    /* the storage is shared by every subclass; separation comes from the cache key always carrying `static::class` */

    /** @var array<string, EnumType> */
    protected static array $enumTypeCache = [];

    /** @var array<class-string, ?string> */
    protected static array $backingTypeCache = [];

    /** @var array<string, string> */
    protected static array $sqlDeclarationCache = [];

    /** not thread-safe: intended for test teardown or CLI warm-up, never for a request handler */
    public static function clearCache(): void
    {
        static::$enumTypeCache = [];
        static::$backingTypeCache = [];
        static::$sqlDeclarationCache = [];
    }

    /**
     * @return array<int, UnitEnum|string|int> raw values in `notEnum` mode, where an override returns scalars
     * @throws Exception if no enum class is configured and the method is not overridden
     */
    public function getValues(): array
    {
        $enumClassName = $this->getEnumClass();

        if (null !== $enumClassName) {
            return $this->getEnumValues();
        }

        throw new Exception(
            \sprintf(
                'you must use the enum class or implement `%s` for type `%s`',
                __FUNCTION__,
                static::getDefaultName(),
            ),
        );
    }

    /** @return class-string<UnitEnum>|null */
    public function getEnumClass(): ?string
    {
        return null;
    }

    /**
     * @param array<string, mixed> $column
     * @throws Exception if no enum class is configured or the class does not exist
     */
    protected function buildSqlDeclaration(string $sqlKeyword, array $column, AbstractPlatform $platform): string
    {
        /* the column copy is key-sorted so identical metadata in a different key order resolves to one entry */
        $normalizedColumn = $column;
        \ksort($normalizedColumn);
        $cacheKey = static::class . '|' . $sqlKeyword . '|' . $platform::class . '|' . \serialize($normalizedColumn);

        if (true === isset(static::$sqlDeclarationCache[$cacheKey])) {
            return static::$sqlDeclarationCache[$cacheKey];
        }

        $quotedValues = [];

        foreach ($this->getValues() as $enumCase) {
            $quotedValues[] = $platform->quoteStringLiteral(
                (string)$this->convertValueToDatabase($enumCase),
            );
        }

        if (true === $platform instanceof AbstractMySQLPlatform) {
            return static::$sqlDeclarationCache[$cacheKey] = $sqlKeyword . '(' . \implode(',', $quotedValues) . ')';
        }

        /* without these defaults `getStringTypeDeclarationSQL()` fails or emits invalid SQL off MySQL */
        $column['length'] ??= 255;
        $column['name'] ??= '';

        return static::$sqlDeclarationCache[$cacheKey] = $this->decorateSqlDeclaration(
            $platform->getStringTypeDeclarationSQL($column),
            $quotedValues,
            $column,
            $platform,
        );
    }

    /**
     * @param array<int, string> $quotedValues
     * @param array<string, mixed> $column
     * @throws Exception if the declaration cannot be decorated for this platform
     */
    protected function decorateSqlDeclaration(
        string $declaration,
        array $quotedValues,
        array $column,
        AbstractPlatform $platform,
    ): string {
        return $declaration;
    }

    /**
     * @throws InvalidTypeValueException if the PHP value is not the expected enum type
     */
    protected function convertValueToDatabase(mixed $phpValue): mixed
    {
        return match ($this->getEnumType()) {
            EnumType::notEnum => $phpValue,
            EnumType::simple => true === $phpValue instanceof UnitEnum
                ? $phpValue->name
                : throw new InvalidTypeValueException(
                    \sprintf('invalid value for type `%s`', static::getDefaultName()),
                ),
            EnumType::backed => true === $phpValue instanceof BackedEnum
                ? $phpValue->value
                : throw new InvalidTypeValueException(
                    \sprintf('invalid value for type `%s`', static::getDefaultName()),
                ),
        };
    }

    /**
     * @throws InvalidTypeValueException if the database value does not match any enum case
     */
    protected function convertValueToPhp(mixed $databaseValue): mixed
    {
        return match ($this->getEnumType()) {
            EnumType::notEnum => $databaseValue,
            EnumType::simple => $this->getEnumByName($databaseValue),
            EnumType::backed => $this->getEnumByValue($databaseValue),
        };
    }

    /**
     * @return array<int, UnitEnum>
     * @throws Exception if no valid enum class is configured
     */
    protected function getEnumValues(): array
    {
        $enumType = $this->getEnumType();

        if (EnumType::notEnum === $enumType) {
            throw new Exception(
                \sprintf('invalid enum class for type `%s`', static::getDefaultName()),
            );
        }

        /** @var class-string<UnitEnum> $enumClassName */
        $enumClassName = $this->getEnumClass();

        return $enumClassName::cases();
    }

    /**
     * @throws Exception if the configured enum class does not exist
     */
    protected function getEnumType(): EnumType
    {
        $calledClassName = static::class;

        if (true === isset(static::$enumTypeCache[$calledClassName])) {
            return static::$enumTypeCache[$calledClassName];
        }

        $enumClassName = $this->getEnumClass();

        if (null === $enumClassName) {
            return static::$enumTypeCache[$calledClassName] = EnumType::notEnum;
        }

        if (false === \enum_exists($enumClassName)) {
            throw new Exception(
                \sprintf(
                    'enum class `%s` does not exist for type `%s`',
                    $enumClassName,
                    static::getDefaultName(),
                ),
            );
        }

        return static::$enumTypeCache[$calledClassName] = true === \is_a($enumClassName, BackedEnum::class, true)
            ? EnumType::backed
            : EnumType::simple;
    }

    /**
     * @throws InvalidTypeValueException if the case name is not a string or does not resolve to an enum case on the enum class
     */
    protected function getEnumByName(mixed $enumCaseName): UnitEnum
    {
        /** @var class-string<UnitEnum> $enumClassName */
        $enumClassName = $this->getEnumClass();

        if (false === \is_string($enumCaseName)) {
            throw new InvalidTypeValueException(
                \sprintf(
                    'expected string enum case name for type `%s`, got `%s`',
                    static::getDefaultName(),
                    true === \is_object($enumCaseName) ? \get_class($enumCaseName) : \gettype($enumCaseName),
                ),
            );
        }

        $constantName = $enumClassName . '::' . $enumCaseName;

        if (false === \defined($constantName)) {
            throw new InvalidTypeValueException(
                \sprintf(
                    'invalid enum value `%s` for type `%s`',
                    $enumCaseName,
                    static::getDefaultName(),
                ),
            );
        }

        $resolvedValue = \constant($constantName);

        /* `defined()` also answers true for a plain class constant, which is not a case */
        if (false === $resolvedValue instanceof UnitEnum || $resolvedValue->name !== $enumCaseName) {
            throw new InvalidTypeValueException(
                \sprintf(
                    'invalid enum value `%s` for type `%s`',
                    $enumCaseName,
                    static::getDefaultName(),
                ),
            );
        }

        return $resolvedValue;
    }

    /**
     * @throws InvalidTypeValueException if the value type does not match the enum backing type or the value does not match any case
     */
    protected function getEnumByValue(mixed $backedEnumValue): BackedEnum
    {
        /** @var class-string<BackedEnum> $enumClassName */
        $enumClassName = $this->getEnumClass();

        if (false === isset(static::$backingTypeCache[$enumClassName])) {
            $reflectionEnum = new ReflectionEnum($enumClassName);
            $backingType = $reflectionEnum->getBackingType();
            static::$backingTypeCache[$enumClassName] = null !== $backingType ? $backingType->getName() : null;
        }

        $backingType = static::$backingTypeCache[$enumClassName];

        if ('int' === $backingType) {
            if (true === \is_int($backedEnumValue)) {
                $normalizedValue = $backedEnumValue;
            } elseif (true === \is_string($backedEnumValue) && 1 === \preg_match('/^-?\d+$/', $backedEnumValue)) {
                $normalizedValue = (int)$backedEnumValue;
            } else {
                throw new InvalidTypeValueException(
                    \sprintf(
                        'expected int or integer-formatted string for type `%s`, got `%s`',
                        static::getDefaultName(),
                        true === \is_object($backedEnumValue) ? \get_class($backedEnumValue) : \gettype($backedEnumValue),
                    ),
                );
            }
        } else {
            if (false === \is_string($backedEnumValue)) {
                throw new InvalidTypeValueException(
                    \sprintf(
                        'expected string for type `%s`, got `%s`',
                        static::getDefaultName(),
                        true === \is_object($backedEnumValue) ? \get_class($backedEnumValue) : \gettype($backedEnumValue),
                    ),
                );
            }

            $normalizedValue = $backedEnumValue;
        }

        $enumCase = $enumClassName::tryFrom($normalizedValue);

        if (null === $enumCase) {
            throw new InvalidTypeValueException(
                \sprintf(
                    'invalid enum value `%s` for type `%s`',
                    $normalizedValue,
                    static::getDefaultName(),
                ),
            );
        }

        return $enumCase;
    }
}
