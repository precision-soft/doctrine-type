# Doctrine Type

[![ci](https://github.com/precision-soft/doctrine-type/actions/workflows/ci.yml/badge.svg)](https://github.com/precision-soft/doctrine-type/actions/workflows/ci.yml)
[![PHP >= 8.2](https://img.shields.io/badge/php-%3E%3D8.2-8892BF)](https://www.php.net/)
[![PHPStan Level 8](https://img.shields.io/badge/phpstan-level%208-brightgreen)](https://phpstan.org/)
[![Code Style PER-CS2.0](https://img.shields.io/badge/code%20style-PER--CS2.0-blue)](https://www.php-fig.org/per/coding-style/)
[![License MIT](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Custom Doctrine DBAL types for MySQL `ENUM`, `SET`, `DATETIME` (with `ON UPDATE`), and `TINYINT` columns, plus a portable enum type that carries its value set onto PostgreSQL and SQLite as a `CHECK` constraint.

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
- **AbstractPortableEnumType** -- the same, plus an inline `CHECK` constraint on the platforms that have no native `ENUM`, so the value set is enforced by the server everywhere.
- **AbstractSetType** -- extend it to map a PHP enum to a MySQL `SET` column. Values are stored as a comma-separated string and hydrated as arrays of enum cases.
- **DateTimeType** -- extends the default Doctrine `DateTimeType` and adds support for `ON UPDATE CURRENT_TIMESTAMP` on MySQL columns.
- **SignedTinyintType** and **UnsignedTinyintType** -- map a MySQL `TINYINT` column and validate the exact range the declaration asks for.
- **TinyintType** -- the original `TINYINT` type, validating the combined signed and unsigned range. Deprecated in favour of the two above.
- **SchemaDiagnostics** -- a read-only inspection of an existing schema that names the columns whose database type has no safe global mapping, exposed as the `doctrine-type-diagnose` command.

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

### Portable Enum

`AbstractEnumType` declares a native `ENUM` on MySQL and a bare `VARCHAR` everywhere else, so off MySQL nothing stops a value that is not an enum case from reaching the column. Extend `AbstractPortableEnumType` instead when the value set has to be enforced by the server on every platform: MySQL keeps its native `ENUM`, while PostgreSQL and SQLite get the same `VARCHAR` plus an inline `CHECK` listing every case.

```php
<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use App\Enum\Status;
use PrecisionSoft\Doctrine\Type\Contract\AbstractPortableEnumType;

class StatusType extends AbstractPortableEnumType
{
    public function getEnumClass(): string
    {
        return Status::class;
    }
}
```

```sql
-- MySQL and MariaDB
status ENUM('active','inactive')
-- PostgreSQL and SQLite
status VARCHAR(255) CHECK (status IN ('active','inactive'))
```

The column name is what the constraint targets, so a declaration reaching a constrained platform without a
`name` key throws `PrecisionSoft\Doctrine\Type\Exception\Exception` rather than constraining the wrong column. Doctrine always supplies it when it builds a table; a hand-rolled call to `getSQLDeclaration()` must pass it. The name is written into the constraint exactly as it arrives: DBAL quotes it where the platform needs quoting (a reserved word such as `order`, or a name declared with quotes) and leaves it bare otherwise, and the constraint follows that decision -- so `"order"` stays `"order"` and an unquoted `Status`, which PostgreSQL folds to `status`, is not turned into a quoted `"Status"` that would name a column the table does not have.

Any platform that is neither MySQL, PostgreSQL nor SQLite gets the plain `VARCHAR` fallback, exactly what
`AbstractEnumType` produces there. The constraint is added by `decorateSqlDeclaration()`, a hook
`AbstractPhpEnumType` calls only on its non-MySQL branch, so overriding
`supportsInlineCheckConstraint()` to include MySQL has no effect -- MySQL never reaches the hook, because its native `ENUM` already carries the value set.

> **PostgreSQL cannot alter such a column.** DBAL emits `ALTER TABLE t ALTER col TYPE <declaration>`, and the
> inline `CHECK` travels with the declaration into a statement PostgreSQL rejects. Creating the table works;
> changing the column later -- adding an enum case, for instance -- means dropping the constraint and
> recreating it by hand. The integration suite pins this, so the day DBAL models the constraint the test
> will say so.

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

Three types map a MySQL `TINYINT` column. `SignedTinyintType` and `UnsignedTinyintType` each declare one signedness and validate exactly the range that declaration allows. `TinyintType` predates them, validates the union of both halves, and is deprecated.

> **MySQL only.** All three require a MySQL platform. Calling `getSQLDeclaration()` on any other platform throws a `PrecisionSoft\Doctrine\Type\Exception\Exception`.

| Type                  | Registered name    | Declaration        | Accepted range |
|-----------------------|--------------------|--------------------|----------------|
| `SignedTinyintType`   | `tinyint_signed`   | `TINYINT`          | -128 to 127    |
| `UnsignedTinyintType` | `tinyint_unsigned` | `TINYINT UNSIGNED` | 0 to 255       |
| `TinyintType`         | `tinyint`          | from `unsigned`    | -128 to 255    |

```php
#[ORM\Column(type: 'tinyint_signed')]
private int $priority = 0;

#[ORM\Column(type: 'tinyint_unsigned')]
private int $level = 0;
```

```php
$signedTinyintType->convertToDatabaseValue(127, $abstractPlatform);    // returns 127 (int)
$signedTinyintType->convertToDatabaseValue('127', $abstractPlatform);  // returns 127 (int)
$signedTinyintType->convertToDatabaseValue(null, $abstractPlatform);   // returns null

$signedTinyintType->convertToDatabaseValue(200, $abstractPlatform);    // throws InvalidTypeValueException
$signedTinyintType->convertToDatabaseValue('abc', $abstractPlatform);  // throws InvalidTypeValueException
```

The variants ignore the `unsigned` column option: the type name is what decides the declaration, so
`tinyint_signed` emits `TINYINT` even for a column mapped with `options: ['unsigned' => true]`. That is what lets conversion know which range it is guarding, and it keeps the declaration and the validation from ever disagreeing.

#### TinyintType, deprecated

`TinyintType` reads the `unsigned` column option to pick its declaration, but Doctrine's
`convertToDatabaseValue()` never receives column metadata, so validation cannot know which half it is in and accepts the combined range instead.

> **The type cannot enforce the column's half of that range.** `200` is valid for an unsigned column and out of
> range for a signed one, and the type accepts it either way. What refuses it is the server, and only while it
> runs in strict mode: with `STRICT_TRANS_TABLES` both MySQL 8.4 and MariaDB 11.8 raise `1264 Out of range
> value`, while a non-strict server silently clamps the value instead. Do not rely on this type to keep a
> signed column inside `-128..127` -- use `SignedTinyintType`.

It still works unchanged, and a migration is a type-name change on the column plus a `Type::addType()` line; the emitted DDL is identical as long as the column's `unsigned` option matches the variant you pick.

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
            - `AbstractPortableEnumType` -- adds an inline `CHECK` on the platforms without a native `ENUM`
        - `AbstractSetType` -- MySQL `SET` column
    - `AbstractTinyintType` -- MySQL `TINYINT` column, with the accepted range left to the subclass
        - `SignedTinyintType` -- `TINYINT`, -128 to 127
        - `UnsignedTinyintType` -- `TINYINT UNSIGNED`, 0 to 255
        - `TinyintType` -- deprecated, the combined -128 to 255 range

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

The practical effect: `doctrine:schema:update` never reports "nothing to update" for these columns, and re-issues the same no-op statement on every run. **The emitted DDL is correct** — the round trip is what is missing. Measured on MySQL 8.4 and MariaDB 11.8, the portable enum also on PostgreSQL 18 and SQLite:

| Column                                        | Round-trips |
|-----------------------------------------------|-------------|
| `AbstractEnumType` (backed, int-backed, pure) | no          |
| `AbstractSetType`                             | no          |
| `SignedTinyintType`                           | yes         |
| `UnsignedTinyintType`                         | no          |
| `TinyintType`, signed                         | yes         |
| `TinyintType`, `unsigned`                     | no          |
| `DateTimeType`, plain                         | yes         |
| `DateTimeType`, `update`                      | no          |
| `AbstractPortableEnumType` off MySQL          | no          |

`SignedTinyintType` round-trips even on a column mapped with `options: ['unsigned' => true]`, because its declaration ignores the option. Everything unsigned lands on DBAL's `tinyint` to `boolean` mapping and never settles, exactly as the plain `TinyintType` did before. `AbstractPortableEnumType` behaves like `AbstractEnumType` on MySQL and never settles on PostgreSQL or SQLite either: its `CHECK` is part of the column declaration DBAL compares, and the introspected column is a plain `VARCHAR`, so the comparator asks for the same `ALTER` on every run (the one PostgreSQL refuses, see *Portable Enum*). The integration suite pins both halves.

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
            tinyint_signed: PrecisionSoft\Doctrine\Type\SignedTinyintType
            tinyint_unsigned: PrecisionSoft\Doctrine\Type\UnsignedTinyintType
            app_status: App\Doctrine\Type\StatusType
            app_roles: App\Doctrine\Type\RolesType
```

### Standalone (without Symfony)

Register types directly with the Doctrine DBAL type system:

```php
use Doctrine\DBAL\Types\Type;
use PrecisionSoft\Doctrine\Type\DateTimeType;
use PrecisionSoft\Doctrine\Type\SignedTinyintType;
use PrecisionSoft\Doctrine\Type\UnsignedTinyintType;
use App\Doctrine\Type\StatusType;

Type::overrideType('datetime', DateTimeType::class);
Type::addType(SignedTinyintType::getDefaultName(), SignedTinyintType::class);
Type::addType(UnsignedTinyintType::getDefaultName(), UnsignedTinyintType::class);
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

## Schema Diagnostics

`SchemaDiagnostics` reads an existing schema and names the columns whose database type has no safe global mapping — the `enum`, `set` and `tinyint` rows of the table in *Schema Stability*. It only reads: it issues no DDL and returns a list of `Diagnostic` objects. The `DateTimeType` rows are out of scope, because
`ON UPDATE CURRENT_TIMESTAMP` is a column attribute rather than a type and no mapping change would settle it.

```php
use Doctrine\DBAL\DriverManager;
use PrecisionSoft\Doctrine\Type\Schema\SchemaDiagnostics;

foreach ((new SchemaDiagnostics())->inspect($connection) as $diagnostic) {
    echo $diagnostic->severity, ' ', $diagnostic->table, '.', $diagnostic->column, ': ', $diagnostic->message;
}
```

The same thing from the shell, on a package installed as a dependency:

```shell
vendor/bin/doctrine-type-diagnose "mysql://root:root@127.0.0.1:3306/app"
```

```
warning	orders.status	enum	Do not map this database type globally; map only this application type with getMappedDatabaseTypes(), or use AbstractPortableEnumType for new constrained columns.
warning	orders.priority	tinyint	Use SignedTinyintType for conversion-time range enforcement; keep database type mapping column-specific.
```

Output is tab-separated, one column per line, on standard output; errors go to standard error. The exit code is `0` when nothing was reported, `1` when at least one diagnostic was, `2` for a missing or surplus argument and `3` for a failure, so it drops into a pipeline as it is; `--help` (or `-h`) prints the usage on standard output and exits with `0`. The url must name a database: both introspection queries filter on the current one, so a url without a path would inspect nothing, and rather than report such a schema as clean the command fails with `error: the connection names no database, nothing was inspected`.

The introspection reads `information_schema` rather than Doctrine's type map, because that map is exactly what hides the problem: DBAL resolves a MySQL `tinyint` column to `boolean` and a `set` column to `simple_array`, so a diagnostic built on the mapped type would never see either. MySQL and MariaDB report `enum`, `set` and
`tinyint` columns (the last split by signedness); PostgreSQL reports columns backed by a `CREATE TYPE ... AS
ENUM` in every schema on the connection's `search_path`, not only the first one. A domain over such a type and an array of it are not reported. Only base tables are inspected -- a view projects a column it cannot redefine, so no type mapping could act on the advice.

Any other platform has no introspection query. `supports()` answers that up front, `inspect()` returns an empty list, and the command says so on standard error instead of exiting quietly as if the schema were clean:

```shell
vendor/bin/doctrine-type-diagnose "sqlite:///app.db"
note: this platform has no introspection query, nothing was inspected
```

## Example application

A runnable product catalogue lives under [`.example/`](./.example/README.md): two tables declared once with every type this package ships — `AbstractPortableEnumType`, `AbstractEnumType`, `AbstractSetType`, `SignedTinyintType`, `UnsignedTinyintType` and `DateTimeType` with `update` — created, written, read, diffed and diagnosed on MySQL, MariaDB, PostgreSQL and SQLite, with the schema churn of the *Schema Stability* table asserted as expected output and `vendor/bin/doctrine-type-diagnose` run as a process. It installs the package from the working tree through a path repository, so it always tests the code as it stands; run it with `.dev/validate/all.sh --example` (which starts the databases) or `cd .example && composer install && composer check`. The directory is `export-ignore`d and never reaches a consumer's `vendor/`.

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
.dev/validate/all.sh --staged   # what the pre-commit hook runs: nothing unless the index carries php or the binary
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

Tests connect through `DATABASE_URL_MYSQL`, `DATABASE_URL_MARIADB` and `DATABASE_URL_POSTGRESQL` and skip themselves when those services are not running, so `composer check` never depends on them. PostgreSQL is there for the two things only it can answer: whether the portable enum's `CHECK` is enforced by the server, and whether an `ALTER` on that column is accepted.

The diagnostics command runs from the repository as `php bin/doctrine-type-diagnose` - `vendor/bin/` carries it only in a project that installs this package as a dependency:

```shell
./dc exec dev php bin/doctrine-type-diagnose "$DATABASE_URL_MYSQL"
```

Build against another PHP version with the `PHP_VERSION` build argument - each version is tagged as its own image, so switching back and forth costs nothing:

```shell
PHP_VERSION=8.4 ./dc build && PHP_VERSION=8.4 ./dc up -d
```

Coverage is available through pcov, which is installed but disabled by default:

```shell
./dc exec dev php -d pcov.enabled=1 vendor/bin/simple-phpunit --coverage-text
```

After editing a file, `./dc restart dev` (a few seconds) is enough to be sure the container is not serving a stale copy - the bind mount can keep the old inode after an atomic rewrite.
