# Doctrine Type

[![ci](https://github.com/precision-soft/doctrine-type/actions/workflows/ci.yml/badge.svg)](https://github.com/precision-soft/doctrine-type/actions/workflows/ci.yml)
[![PHP >= 8.2](https://img.shields.io/badge/php-%3E%3D8.2-8892BF)](https://www.php.net/)
[![PHPStan Level 8](https://img.shields.io/badge/phpstan-level%208-brightgreen)](https://phpstan.org/)
[![Code Style PER-CS2.0](https://img.shields.io/badge/code%20style-PER--CS2.0-blue)](https://www.php-fig.org/per/coding-style/)
[![License MIT](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Custom Doctrine DBAL types for MySQL `ENUM`, `SET`, `DATETIME` (with `ON UPDATE`), and `TINYINT` columns.

This library provides abstract base classes you can extend to define your own Doctrine types backed by PHP enums, as well as ready-to-use types for `DATETIME` and `TINYINT`.

Supports Doctrine DBAL 4, PHP 8.2+.

**You may fork and modify it as you wish.**

Any suggestions are welcomed.

## Requirements

- PHP 8.2+
- Doctrine DBAL 4

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

$statusType->convertToDatabaseValue(Status::Active, $mysqlPlatform);  // returns 'active'
$statusType->convertToPHPValue('active', $mysqlPlatform);             // returns Status::Active
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

$rolesType->convertToDatabaseValue([Role::Admin, Role::Editor], $mysqlPlatform);  // returns 'admin,editor'
$rolesType->convertToPHPValue('admin,editor', $mysqlPlatform);                    // returns [Role::Admin, Role::Editor]
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

> **MySQL only.** `TinyintType` requires a MySQL platform. Calling `getSQLDeclaration()` on any other platform throws a `PrecisionSoft\Doctrine\Type\Exception\Exception`.

```php
#[ORM\Column(type: 'tinyint')]
private int $priority = 0;

#[ORM\Column(type: 'tinyint', options: ['unsigned' => true])]
private int $level = 0;
```

**Range validation:** values are validated on write. Since Doctrine's `convertToDatabaseValue` does not receive column metadata, the combined range (-128 to 255) is accepted by default. The `getSQLDeclaration` method uses the `unsigned` column option to generate the correct SQL (`tinyint` or `tinyint UNSIGNED`).

> **The type cannot enforce the column's half of that range.** `200` is valid for an unsigned column and out of
> range for a signed one, and the type accepts it either way. What refuses it is the server, and only while it
> runs in strict mode: with `STRICT_TRANS_TABLES` both MySQL 8.4 and MariaDB 11.4 raise `1264 Out of range
> value`, while a non-strict server silently clamps the value instead. Do not rely on this type to keep a
> signed column inside `-128..127`.

**Converting values:**

```php
$tinyintType->convertToDatabaseValue(42, $abstractPlatform);    // returns 42 (int)
$tinyintType->convertToDatabaseValue('100', $abstractPlatform);  // returns 100 (int)
$tinyintType->convertToDatabaseValue(null, $abstractPlatform);   // returns null

$tinyintType->convertToDatabaseValue(256, $abstractPlatform);    // throws InvalidTypeValueException
$tinyintType->convertToDatabaseValue('abc', $abstractPlatform);  // throws InvalidTypeValueException
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

### Schema Stability

A custom type declares a column, but Doctrine cannot tell from the database that the column belongs to *that*
type. On introspection MySQL and MariaDB report an `enum` column as DBAL's own `enum` type, a `set` column as
`simple_array`, and a `tinyint` column as `boolean`. The schema comparator then compares the two column declarations as strings, finds them different, and asks for an `ALTER TABLE` — one that declares exactly the column already in place.

The practical effect: `doctrine:schema:update` never reports "nothing to update" for these columns, and re-issues the same no-op statement on every run. **The emitted DDL is correct** — the round trip is what is missing. Measured on MySQL 8.4 and MariaDB 11.4:

| Column                                        | Round-trips |
|-----------------------------------------------|-------------|
| `AbstractEnumType` (backed, int-backed, pure) | no          |
| `AbstractSetType`                             | no          |
| `TinyintType`, signed                         | yes         |
| `TinyintType`, `unsigned`                     | no          |
| `DateTimeType`, plain                         | yes         |
| `DateTimeType`, `update`                      | no          |

If a settled schema matters to you, declare which database type your type owns. DBAL asks every registered type for this and uses the answer when it introspects, so both sides of the comparison are then produced by the same declaration code:

```php
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PrecisionSoft\Doctrine\Type\Contract\AbstractEnumType;

class StatusType extends AbstractEnumType
{
    public function getEnumClass(): ?string
    {
        return Status::class;
    }

    /** @return array<int, string> */
    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return ['enum'];
    }
}
```

This is deliberately **not** the default, because the claim is global and exclusive:

- Only one registered type can own a database type name; the last one registered wins. An application with two enum types cannot settle both this way — pick the one whose schema churn actually costs you something.
- Owning `tinyint` also takes over every `boolean` column, because MySQL booleans are stored as `tinyint`.

`DateTimeType` with `update` cannot be settled at all: DBAL does not model `ON UPDATE CURRENT_TIMESTAMP`, so introspection cannot see it and the desired column always carries something the introspected one does not. A column using it will always show a pending `ALTER`. The clause itself works — the server applies it on every
`UPDATE`, which the integration suite verifies against both engines.

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

## Exception context

Every exception in this package carries a structured `context` array next to its message, so the facts describing a failure do not have to be parsed back out of a string:

```php
try {
    // ...
} catch (Exception $exception) {
    $logger->error($exception->getMessage(), $exception->getContext());
}
```

`getContext()` returns `[]` when nothing was attached. `setContext()` replaces it and returns the exception, and the constructor accepts it as an optional fourth argument. Values are expected to be scalars, so the array stays serialisable by a logger.

The context is purely **additive**: no message, code or previous throwable changed when it was introduced, so code that logs only `getMessage()` behaves exactly as before.

Nothing in this package attaches a context of its own — it never catches and re-wraps a foreign throwable — so the capability exists for consumers extending `Exception` or `InvalidTypeValueException` in their own types. Note that the base exception implements **both** `Contract\ExceptionInterface` and Doctrine's own `Doctrine\DBAL\Exception` marker, so an existing `catch (Doctrine\DBAL\Exception)` still holds.

Every exception in the package implements `Contract\ExceptionInterface`, so a consumer can read the context off any of them without knowing the concrete class. A subclass of your own that already declares a `$context` property or a
`getContext()`/`setContext()` method will collide with `Exception\Trait\ExceptionTrait`.

## Dev

The development environment uses Docker. The `./dc` script is a Docker Compose wrapper located in `.dev/`.

```shell
git clone git@github.com:precision-soft/doctrine-type.git
cd doctrine-type

./dc build && ./dc up -d
```

Run the full gate the way the pre-commit hook runs it - the CI workflow in
`.github/workflows/ci.yml` calls the same composer scripts, so the two cannot drift:

```shell
.dev/validate/all.sh
.dev/validate/all.sh --audit    # also audits the locked dependencies ( needs the network )
.dev/validate/all.sh --staged   # what the pre-commit hook runs: nothing unless the index carries php
```

Mutation testing is opt-in for the same reason, plus cost - it runs the suite once per mutant:

```shell
.dev/validate/all.sh --mutation
```

Infection is a pinned phar in the image, not a composer dependency, and `infection.json5` carries a
`minMsi` floor equal to the last measured score, so the section fails when a change makes the suite weaker rather than only reporting a number. Raise the floor when the score improves.

The integration suite needs real databases, which are behind a Compose profile so the default `up`
stays fast and offline:

```shell
./dc --profile db up -d
.dev/validate/all.sh --integration
```

Tests connect through `DATABASE_URL_MYSQL` and `DATABASE_URL_MARIADB` and skip themselves when those services are not running, so `composer check` never depends on them.

Build against another PHP version with the `PHP_VERSION` build argument - each version is tagged as its own image, so switching back and forth costs nothing:

```shell
PHP_VERSION=8.4 ./dc build && PHP_VERSION=8.4 ./dc up -d
```

Coverage is available through pcov, which is installed but disabled by default:

```shell
./dc exec dev php -d pcov.enabled=1 vendor/bin/simple-phpunit --coverage-text
```

After editing a file, `./dc restart dev` (a few seconds) is enough to be sure the container is not serving a stale copy - the bind mount can keep the old inode after an atomic rewrite.
