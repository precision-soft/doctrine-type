<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Contract;

use Doctrine\DBAL\Types\Type;
use ReflectionClass;

abstract class AbstractType extends Type
{
    /** @var array<class-string, string> */
    protected static array $defaultNameCache = [];

    /**
     * Can be used if you have multiple databases with entities that have the
     * same name, to easily have distinct types by using the entity manager
     * name as a prefix.
     */
    public static function getDefaultNamePrefix(): ?string
    {
        return null;
    }

    public static function getDefaultName(): string
    {
        return self::$defaultNameCache[static::class]
            ??= (static::getDefaultNamePrefix() ?? '') . (new ReflectionClass(static::class))->getShortName();
    }
}
