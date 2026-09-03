# Doctrine Type — example

The product catalogue of a shop — two tables, `category` and `product`, declared once with every column type this library ships — created, written, read, diffed and diagnosed on the real engines the development stack runs (MySQL 8.4, MariaDB 11.8, PostgreSQL 18, plus an in-memory SQLite). It is the minimum of code that shows the maximum of `precision-soft/doctrine-type` the way an application without an ORM uses it: DBAL `Schema` objects, `Connection::insert()` with a type map, `Type::getType()->convertToPHPValue()` on the way back.

Paths in this file are relative to `.example/`.

## What it represents

- `src/Enum/` — `ProductStatus` and `Currency` (string-backed) and `SalesChannel` (pure), the nomenclator's enumerations.
- `src/Type/` — the three application types: `ProductStatusType` (`AbstractPortableEnumType`: a native `ENUM` on MySQL, a `VARCHAR` with a `CHECK` elsewhere), `CurrencyType` (`AbstractEnumType`: a native `ENUM` on MySQL, a plain `VARCHAR` elsewhere) and `SalesChannelSetType` (`AbstractSetType`: a MySQL `SET` of case names).
- `src/Schema/CatalogSchema.php` — the two tables for whatever platform the connection has: `SignedTinyintType` and `UnsignedTinyintType` on MySQL (they declare MySQL columns only), the platform's small integer elsewhere; `DateTimeType` with `update` for `product.modified`; a quoted reserved word, `"order"`, as a column name. `registerTypes()` puts the types into DBAL's global registry once.
- `src/Service/CatalogRepository.php` — creates the schema, writes a category and a product through the types (the type map is what makes `UnsignedTinyintType` refuse `-1` before the server sees it) and reads a product back through `convertToPHPValue()`.
- `src/Service/SchemaReport.php` — the columns `doctrine:schema:update` would keep asking an `ALTER` for (the README's *Schema Stability* table, measured), and the catalogue's rows of `SchemaDiagnostics`.

## What each test shows

| Test file                                   | Library capability demonstrated                                                                                                                                                                                                                                                                                                                                                                                        |
|---------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `tests/Functional/CatalogRoundTripTest.php` | Every type written and read back on the three engines (enum case, set of cases, signed tinyint, `DateTime`); the portable enum refused **by the server** on MySQL, MariaDB, PostgreSQL and SQLite for a raw insert that bypasses the type; the tinyint variants refusing `-1` and `200` at conversion time with the library's message; `ON UPDATE CURRENT_TIMESTAMP` rewriting `product.modified` on MySQL and MariaDB |
| `tests/Functional/SchemaReportTest.php`     | The schema churn as expected output, not as a failure: on MySQL and MariaDB every column but the signed tinyint asks for an `ALTER` on every run and `SchemaDiagnostics` names the five columns (`enum` ×2, `set`, `tinyint` ×2); on PostgreSQL only the portable enum churns (its `CHECK` is part of the declaration) and there is nothing to diagnose                                                                |
| `tests/Functional/DiagnoseBinaryTest.php`   | `vendor/bin/doctrine-type-diagnose` run as a process, the way a consumer runs it: exit `1` and the five tab-separated rows on MySQL and MariaDB, exit `0` on PostgreSQL, `--help`                                                                                                                                                                                                                                      |

Two things worth knowing before writing a scenario of your own: `DateTimeType` extends DBAL's own, so it converts a mutable `DateTime` only — a `DateTimeImmutable` is refused by DBAL, not by this library; and the type registry is global to the process, so `CatalogSchema::registerTypes()` is guarded and the tests share the `test` database with the library's own suites, dropping the two tables before and after each run.

## How to run

The example installs the library from the working tree through a path repository, so it always tests the code as it stands. Its `composer.lock` is not committed: a fresh install resolves the dependencies at that moment, and the root's `composer.lock` stays the reproducible set. The tests need the three databases of the `db` Compose profile and fail — not skip — when one is missing (`--fail-on-skipped`).

```shell
cd /var/www/html && .dev/validate/all.sh --example        # starts the databases, installs, runs phpstan and the tests
cd /var/www/html/.example && composer install && composer check
```

The root's `composer cs-check` covers this directory too, and `phpstan.neon` includes the house rules from `../.dev/phpstan/rules.neon`. The directory is `export-ignore`d, so nothing here reaches a consumer's `vendor/`.
