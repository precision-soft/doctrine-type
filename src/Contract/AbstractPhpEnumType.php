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
        return match ($this->getEnumType()) {
            EnumType::notEnum => $value,
            EnumType::simple => true === $value instanceof UnitEnum
                ? $value->name
                : throw new InvalidTypeValueException(
                    sprintf('invalid value for type `%s`', static::getDefaultName()),
                ),
            EnumType::backed => true === $value instanceof BackedEnum
                ? $value->value
                : throw new InvalidTypeValueException(
                    sprintf('invalid value for type `%s`', static::getDefaultName()),
                ),
        };
    }

    public function convertValueToPhp(mixed $value): mixed
    {
        return match ($this->getEnumType()) {
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
        $class = static::class;

        if (true === isset(self::$enumTypeCache[$class])) {
            return self::$enumTypeCache[$class];
        }

        $className = $this->getEnumClass();

        if (null === $className) {
            return self::$enumTypeCache[$class] = EnumType::notEnum;
        }

        if (false === class_exists($className) || false === enum_exists($className)) {
            throw new Exception(
                sprintf(
                    'enum class `%s` does not exist for type `%s`',
                    $className,
                    static::getDefaultName(),
                ),
            );
        }

        return self::$enumTypeCache[$class] = true === is_a($className, BackedEnum::class, true)
            ? EnumType::backed
            : EnumType::simple;
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
        $case = $this->getEnumClass()::tryFrom($value);

        if (null === $case) {
            throw new InvalidTypeValueException(
                sprintf(
                    'invalid enum value `%s` for type `%s`',
                    $value,
                    static::getDefaultName(),
                ),
            );
        }

        return $case;
    }
}
