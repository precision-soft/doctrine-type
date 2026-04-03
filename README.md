# Doctrine Type

Custom Doctrine DBAL types for MySQL `ENUM`, `SET`, `DATETIME` (with `ON UPDATE`), and `TINYINT` columns.

This library provides abstract base classes you can extend to define your own Doctrine types backed by PHP enums, as well as ready-to-use types for `DATETIME` and `TINYINT`.

Supports Doctrine DBAL 4, PHP 8.2+.

## What It Does

Doctrine DBAL does not ship native support for MySQL-specific column types such as `ENUM`, `SET`, or `TINYINT`. This library fills that gap by providing:

- **AbstractEnumType** -- extend it to map a PHP enum to a MySQL `ENUM` column. On non-MySQL platforms it falls back to the platform's string type.
- **AbstractSetType** -- extend it to map a PHP enum to a MySQL `SET` column. Values are stored as a comma-separated string and hydrated as arrays of enum cases.
- **DateTimeType** -- extends the default Doctrine `DateTimeType` and adds support for `ON UPDATE CURRENT_TIMESTAMP` on MySQL columns.
- **TinyintType** -- maps a MySQL `TINYINT` column (signed or unsigned) with range validation.

All types use project-specific exceptions so you can catch type-related errors without catching unrelated exceptions:

- `PrecisionSoft\Doctrine\Type\Exception\Exception` -- base exception for all type errors (e.g. unsupported platform).
- `PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException` -- thrown when a value fails validation (wrong type, out of range, invalid enum case).

## Installation

```shell
composer require precision-soft/doctrine-type
```

## Types

### Enum

Extend `AbstractEnumType` and point it at a PHP enum (backed or unit).

```php
<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use App\Enum\Status;
use PrecisionSoft\Doctrine\Type\Contract\AbstractEnumType;

class StatusType extends AbstractEnumType
{
    public function getEnumClass(): string
    {
        return Status::class;
    }
}
```

Where the enum is either a backed enum or a simple (unit) enum:

```php
<?php

declare(strict_types=1);

namespace App\Enum;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
```

The SQL declaration on MySQL will be `ENUM('active','inactive')`. On other platforms it falls back to the platform's string type.

**Converting values:**

```php
use Doctrine\DBAL\Platforms\MySQLPlatform;

$statusType = new StatusType();
$mysqlPlatform = new MySQLPlatform();

$statusType->convertToDatabaseValue(Status::Active, $mysqlPlatform);  /** @info returns 'active' */
$statusType->convertToPHPValue('active', $mysqlPlatform);             /** @info returns Status::Active */
```

### Set

Extend `AbstractSetType` the same way. Values are stored as a comma-separated string and hydrated as arrays of enum cases.

```php
<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use App\Enum\Role;
use PrecisionSoft\Doctrine\Type\Contract\AbstractSetType;

class RolesType extends AbstractSetType
{
    public function getEnumClass(): string
    {
        return Role::class;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Enum;

enum Role: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';
}
```

On MySQL the SQL declaration will be `SET('admin','editor','viewer')`. PHP values are hydrated as arrays of enum cases.

**Converting values:**

```php
use Doctrine\DBAL\Platforms\MySQLPlatform;

$rolesType = new RolesType();
$mysqlPlatform = new MySQLPlatform();

$rolesType->convertToDatabaseValue([Role::Admin, Role::Editor], $mysqlPlatform);  /** @info returns 'admin,editor' */
$rolesType->convertToPHPValue('admin,editor', $mysqlPlatform);                    /** @info returns [Role::Admin, Role::Editor] */
```

### DateTime

`DateTimeType` extends the default Doctrine `DateTimeType` and adds support for `ON UPDATE CURRENT_TIMESTAMP` on MySQL columns. Set the `update` option in your column definition:

```php
#[ORM\Column(type: 'datetime', options: ['update' => true])]
private ?\DateTimeInterface $updatedAt = null;
```

The generated SQL on MySQL will append `ON UPDATE CURRENT_TIMESTAMP` to the column declaration. On other platforms it behaves identically to the default Doctrine `DateTimeType`.

### Tinyint

`TinyintType` maps a MySQL `TINYINT` column. It supports both signed (-128 to 127) and unsigned (0 to 255) declarations:

```php
#[ORM\Column(type: 'tinyint')]
private int $priority = 0;

#[ORM\Column(type: 'tinyint', options: ['unsigned' => true])]
private int $level = 0;
```

**Range validation:** values are validated on write. Since Doctrine's `convertToDatabaseValue` does not receive column metadata, the combined range (-128 to 255) is accepted by default. The `getSQLDeclaration` method uses the `unsigned` column option to generate the correct SQL (`tinyint` or `tinyint UNSIGNED`).

**Converting values:**

```php
$tinyintType->convertToDatabaseValue(42, $abstractPlatform);    /** @info returns 42 (int) */
$tinyintType->convertToDatabaseValue('100', $abstractPlatform);  /** @info returns 100 (int) */
$tinyintType->convertToDatabaseValue(null, $abstractPlatform);   /** @info returns null */

$tinyintType->convertToDatabaseValue(256, $abstractPlatform);    /** @info throws InvalidTypeValueException */
$tinyintType->convertToDatabaseValue('abc', $abstractPlatform);  /** @info throws InvalidTypeValueException */
```

### Multi-Database Prefix

If you have multiple databases with entities sharing the same type name, override `getDefaultNamePrefix()` to distinguish them:

```php
<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use App\Enum\Status;
use PrecisionSoft\Doctrine\Type\Contract\AbstractEnumType;

class StatusType extends AbstractEnumType
{
    public static function getDefaultNamePrefix(): ?string
    {
        return 'billing_';
    }

    public function getEnumClass(): string
    {
        return Status::class;
    }
}
```

`StatusType::getDefaultName()` will return `billing_StatusType` instead of `StatusType`.

### Type Hierarchy

All custom types extend `AbstractType`, which provides `getDefaultName()` and `getDefaultNamePrefix()`. Enum and set types add PHP enum support through an intermediate class:

- `AbstractType` -- base for all custom types (extends Doctrine `Type`)
    - `AbstractPhpEnumType` -- adds PHP enum resolution and caching
        - `AbstractEnumType` -- MySQL `ENUM` column
        - `AbstractSetType` -- MySQL `SET` column
    - `TinyintType` -- MySQL `TINYINT` column

`DateTimeType` extends the built-in Doctrine `DateTimeType` directly (not `AbstractType`) because it overrides the default `datetime` type rather than registering a new one.

### Cache

`AbstractPhpEnumType` caches enum type resolution per class. To clear the cache (useful in tests):

```php
use PrecisionSoft\Doctrine\Type\Contract\AbstractPhpEnumType;

AbstractPhpEnumType::clearCache();
```

## Configuration

### Symfony

Register the types in your `doctrine.yaml`:

```yaml
doctrine:
    dbal:
        default_connection: master
        connections:
            master:
                url: '%env(resolve:DATABASE_URL)%'
                server_version: '%env(MYSQL_SERVER_VERSION)%'
                mapping_types:
                    enum: string
                    set: string
        types:
            datetime: PrecisionSoft\Doctrine\Type\DateTimeType
            tinyint: PrecisionSoft\Doctrine\Type\TinyintType
            app_status: App\Doctrine\Type\StatusType
            app_roles: App\Doctrine\Type\RolesType
```

### Standalone (without Symfony)

Register types directly with the Doctrine DBAL type system:

```php
use Doctrine\DBAL\Types\Type;
use PrecisionSoft\Doctrine\Type\DateTimeType;
use PrecisionSoft\Doctrine\Type\TinyintType;
use App\Doctrine\Type\StatusType;

Type::overrideType('datetime', DateTimeType::class);
Type::addType('tinyint', TinyintType::class);
Type::addType(StatusType::getDefaultName(), StatusType::class);
```

## Dev

```shell
git clone git@github.com:precision-soft/doctrine-type.git
cd doctrine-type

# docker_compose is a wrapper from .dev/utility.sh
source .dev/utility.sh
docker_compose build && docker_compose up -d
```
