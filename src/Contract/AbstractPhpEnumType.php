<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Contract;

use BackedEnum;
use PrecisionSoft\Doctrine\Type\Enum\EnumType;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use ReflectionEnum;
use UnitEnum;

abstract class AbstractPhpEnumType extends AbstractType
{
    /** @var array<string, EnumType> */
    private static array $enumTypeCache = [];

    /**
     * @return array<int, mixed>
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

    /** @return class-string<UnitEnum>|class-string<BackedEnum>|null */
    public function getEnumClass(): ?string
    {
        return null;
    }

    public static function clearCache(): void
    {
        self::$enumTypeCache = [];
    }

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

    protected function convertValueToPhp(mixed $databaseValue): mixed
    {
        return match ($this->getEnumType()) {
            EnumType::notEnum => $databaseValue,
            EnumType::simple => $this->getEnumByName($databaseValue),
            EnumType::backed => $this->getEnumByValue($databaseValue),
        };
    }

    /**
     * @return array<int, UnitEnum|BackedEnum>
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

    private function getEnumType(): EnumType
    {
        $calledClassName = static::class;

        if (true === isset(self::$enumTypeCache[$calledClassName])) {
            return self::$enumTypeCache[$calledClassName];
        }

        $enumClassName = $this->getEnumClass();

        if (null === $enumClassName) {
            return self::$enumTypeCache[$calledClassName] = EnumType::notEnum;
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

        return self::$enumTypeCache[$calledClassName] = true === \is_a($enumClassName, BackedEnum::class, true)
            ? EnumType::backed
            : EnumType::simple;
    }

    private function getEnumByName(mixed $enumCaseName): mixed
    {
        /** @var class-string<UnitEnum> $enumClassName */
        $enumClassName = $this->getEnumClass();
        $result = null;

        foreach ($enumClassName::cases() as $enumCase) {
            if ($enumCaseName === $enumCase->name) {
                $result = $enumCase;

                break;
            }
        }

        if (null === $result) {
            throw new InvalidTypeValueException(
                \sprintf(
                    'invalid enum value `%s` for type `%s`',
                    $enumCaseName,
                    static::getDefaultName(),
                ),
            );
        }

        return $result;
    }

    private function getEnumByValue(mixed $backedEnumValue): mixed
    {
        $enumClassName = $this->getEnumClass();

        if (null === $enumClassName) {
            throw new Exception(
                \sprintf('enum class is null for type `%s`', static::getDefaultName()),
            );
        }

        /** @var class-string<BackedEnum> $enumClassName */
        $reflectionEnum = new ReflectionEnum($enumClassName);
        $backingType = $reflectionEnum->getBackingType();

        if (null !== $backingType && 'int' === $backingType->getName()) {
            $backedEnumValue = (int)$backedEnumValue;
        }

        $enumCase = $enumClassName::tryFrom($backedEnumValue);

        if (null === $enumCase) {
            throw new InvalidTypeValueException(
                \sprintf(
                    'invalid enum value `%s` for type `%s`',
                    $backedEnumValue,
                    static::getDefaultName(),
                ),
            );
        }

        return $enumCase;
    }
}
