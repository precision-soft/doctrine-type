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
use ReflectionClass;
use UnitEnum;

abstract class AbstractPhpEnumType extends AbstractType
{
    private ?EnumType $enumType = null;

    /**
     * @return array<int, mixed>
     */
    public function getValues(): array
    {
        $enumClass = $this->getEnumClass();

        if (null !== $enumClass) {
            return $this->getEnumValues();
        }

        throw new Exception(
            sprintf(
                'you must use the enum class or implement `%s` for type `%s`',
                __FUNCTION__,
                static::getDefaultName(),
            ),
        );
    }

    /**
     * The recommended way to use these types if you use a version of PHP with enums.
     *
     * @return class-string<UnitEnum>|class-string<BackedEnum>|null
     */
    public function getEnumClass(): ?string
    {
        return null;
    }

    public function convertValueToDatabase(mixed $value): mixed
    {
        $enumType = $this->getEnumType();

        switch ($enumType) {
            case EnumType::notEnum:
                return $value;
            case EnumType::simple:
                if ($value instanceof UnitEnum) {
                    return $value->name;
                }
                break;
            case EnumType::backed:
                if ($value instanceof BackedEnum) {
                    return $value->value;
                }
                break;
        }

        throw new InvalidTypeValueException(
            sprintf('invalid value for type `%s`', static::getDefaultName()),
        );
    }

    public function convertValueToPhp(mixed $value): mixed
    {
        $enumType = $this->getEnumType();

        return match ($enumType) {
            EnumType::notEnum => $value,
            EnumType::simple => $this->getEnumByName($value),
            EnumType::backed => $this->getEnumByValue($value),
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
                sprintf('invalid enum class for type `%s`', static::getDefaultName()),
            );
        }

        return $this->getEnumClass()::cases();
    }

    private function getEnumType(): EnumType
    {
        /** @todo find a better way to cache this */
        if (null !== $this->enumType) {
            return $this->enumType;
        }

        $className = $this->getEnumClass();

        if (null === $className) {
            $this->enumType = EnumType::notEnum;

            return $this->enumType;
        }

        if (
            false === class_exists($className)
            || false === enum_exists($className)
        ) {
            throw new Exception(
                sprintf(
                    'enum class `%s` does not exist for type `%s`',
                    $className,
                    static::getDefaultName(),
                ),
            );
        }

        $reflection = new ReflectionClass($className);

        if (false === $reflection->isEnum()) {
            $this->enumType = EnumType::notEnum;

            return $this->enumType;
        }

        $this->enumType = true === $reflection->implementsInterface(BackedEnum::class)
            ? EnumType::backed
            : EnumType::simple;

        return $this->enumType;
    }

    private function getEnumByName(mixed $value): mixed
    {
        $className = $this->getEnumClass();

        foreach ($className::cases() as $case) {
            if ($value === $case->name) {
                return $case;
            }
        }

        throw new InvalidTypeValueException(
            sprintf(
                'invalid enum value `%s` for type `%s`',
                $value,
                static::getDefaultName(),
            ),
        );
    }

    private function getEnumByValue(mixed $value): mixed
    {
        $className = $this->getEnumClass();

        foreach ($className::cases() as $case) {
            if ($value === $case->value) {
                return $case;
            }
        }

        throw new InvalidTypeValueException(
            sprintf(
                'invalid enum value `%s` for type `%s`',
                $value,
                static::getDefaultName(),
            ),
        );
    }
}
