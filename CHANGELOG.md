# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v3.7.0] - 2026-09-03 - Constraints on quoted column names, diagnostics that never report an uninspected schema as clean, and the example application

### Added

- `doctrine-type-diagnose --help` (or `-h`) prints the usage on standard output and exits with `0`. Until now the flag was taken for a database url, so the command tried to connect to it and exited with `3` and a driver error instead of telling what it expects
- `.example/` — a runnable product catalogue whose test suite exercises every type of the package on the real engines: the enum, set, portable enum, tinyint and on-update columns created, written and read back on MySQL, MariaDB and PostgreSQL (the portable `CHECK` also on SQLite), the schema churn of the README's *Schema Stability* table asserted as expected output, `SchemaDiagnostics` on the catalogue and `doctrine-type-diagnose` run as a process. It installs the package from the working tree through a path repository and is gated by `.dev/validate/all.sh --example` and the `example` CI job, which now carries the three database services; the directory is `export-ignore`d, so nothing reaches a consumer's `vendor/`
- `Command\SchemaDiagnosticsCommand` takes the standard output and standard error streams as constructor arguments, defaulting to the process's own, so the command can be driven and read in a test without touching the process streams. The command and the binary had no test until now; they have a unit suite over memory streams and a functional suite that runs `bin/doctrine-type-diagnose` as a process against the three engines

### Fixed

- `Contract\AbstractPortableEnumType` writes the column name into the `CHECK` exactly as DBAL supplies it. DBAL hands the declaration the name it has already quoted where the platform needs quoting and bare otherwise, and the type quoted it a second time: a column declared as `"order"` produced `CHECK ("""order""" IN (...))`, which PostgreSQL rejects as a column that does not exist and SQLite reads as a string literal that is never in the list, so every insert failed; an unquoted `Status`, which PostgreSQL folds to `status`, produced `CHECK ("Status" IN (...))` and the table could not be created. Only lower-case, non-reserved names ever worked; those are unchanged
- `Schema\SchemaDiagnostics::inspect()` refuses a connection whose url names no database. Both introspection queries filter on the current database, which is `NULL` on such a connection, so the query matched nothing and `doctrine-type-diagnose mysql://root:root@127.0.0.1` exited with `0` as if the schema were clean. It now throws `Exception\Exception` and the command exits with `3`
- `Schema\SchemaDiagnostics` looks for PostgreSQL enum columns in every schema on the connection's `search_path` (`current_schemas(false)`), where it looked in `current_schema()` only -- the first entry. An application keeping its tables in a schema behind `public` on the path got an empty, clean report
- `doctrine-type-diagnose` exits with `2` and the usage when it receives more than one argument, where it silently ignored everything after the first one

## [v3.6.0] - 2026-09-01 - Portable enum constraints, explicit tinyint ranges and a schema diagnostics command

### Added

- `Contract\AbstractPortableEnumType` — an opt-in enum type whose value set is enforced by the server on every platform, not only on MySQL. `AbstractEnumType` emits a native `ENUM` on MySQL and a bare `VARCHAR` everywhere else, which means that off MySQL nothing refuses a value that is not an enum case; the portable type keeps the native `ENUM` where there is one and appends an inline `CHECK` listing every case on PostgreSQL and SQLite. The column name is required on those platforms — a declaration without a `name` key throws rather than constraining the wrong column. **One limitation is real and now pinned by a test:** DBAL emits `ALTER TABLE t ALTER col TYPE <declaration>` on PostgreSQL, and the inline `CHECK` travels with the declaration into a statement PostgreSQL rejects, so the column can be created but not later altered. The functional suite executes that `ALTER` against PostgreSQL 17 and asserts the rejection, so the day DBAL models the constraint the test will say so
- `SignedTinyintType` (`tinyint_signed`, `TINYINT`, -128 to 127) and `UnsignedTinyintType` (`tinyint_unsigned`, `TINYINT UNSIGNED`, 0 to 255) — the pair that closes the hole `TinyintType` documented but could not fix. `convertToDatabaseValue()` never receives column metadata, so a single type cannot know which half of the range it is guarding and has to accept the union; naming the signedness in the type moves that decision to a place conversion can see. Each variant ignores the `unsigned` column option on purpose: the type name decides the declaration, so the DDL and the validated range can never disagree. Measured against MySQL 8.4 and MariaDB 11.4, `SignedTinyintType` round-trips through `doctrine:schema:update` even on a column mapped `['unsigned' => true]`, while `UnsignedTinyintType` inherits the unsigned `TINYINT` churn already documented in the README table
- `Schema\SchemaDiagnostics`, `Schema\Diagnostic`, `Command\SchemaDiagnosticsCommand` and the `doctrine-type-diagnose` binary — a read-only pass over an existing schema that names the columns whose database type has no safe global mapping — the `enum`, `set` and `tinyint` rows of the *Schema Stability* table — and says what to do about each. It issues no DDL, and it does not look at the `DateTimeType` rows: `ON UPDATE CURRENT_TIMESTAMP` is a column attribute rather than a type, and there is no mapping change that would settle it. The introspection deliberately reads `information_schema` rather than Doctrine's type map, because that map is what hides the problem: DBAL resolves a MySQL `tinyint` column to `boolean` and a `set` column to `simple_array`, so a diagnostic built on the mapped type would report neither. MySQL and MariaDB report `enum`, `set` and `tinyint` columns with the last split by signedness; PostgreSQL reports columns backed by a `CREATE TYPE ... AS ENUM`. Only base
  tables are inspected: a view projects a column it cannot redefine, so nothing could act on the advice. `SchemaDiagnostics::supports()` answers whether a platform has an introspection query at all, and the command prints a note on standard error rather than exiting quietly as if an unsupported schema were clean. Output is tab-separated on standard output and errors on standard error; the exit code is `0` with nothing to report, `1` with at least one diagnostic, `2` on a missing argument and `3` on failure, so it drops into a pipeline unchanged. The binary resolves its autoloader through Composer's own `_composer_autoload_path` before falling back to the two conventional relative paths, so it runs from a checkout, from `vendor/bin` and from a project with a custom `vendor-dir`; it reads `$_SERVER['argv']` rather than the `$argv` global, which exists only while `register_argc_argv` is on
- `Contract\AbstractPhpEnumType::decorateSqlDeclaration()` — the extension point the portable type is built on. `buildSqlDeclaration()` calls it on the non-MySQL branch, after the platform's string declaration and before the result reaches `$sqlDeclarationCache`, and the default implementation returns the declaration untouched. Existing subclasses see no change; the portable type gets the values already quoted by `buildSqlDeclaration()` instead of quoting them a second time, and its declaration is cached like every other
- `Contract\AbstractTinyintType` — the shared base for the three tinyint types, so nothing has to extend a deprecated class to reuse its parsing. Subclasses supply `isWithinRange()` and `getRangeDescription()`; everything else is inherited
- PostgreSQL 17 in the development stack and in CI — `pdo_pgsql` in the image, a `postgresql` service behind the existing `db` Compose profile, `DATABASE_URL_POSTGRESQL`, and the service added to `.dev/validate/all.sh --integration`. It is there for the two questions only a PostgreSQL server can answer: whether the portable `CHECK` is enforced, and whether an `ALTER` on that column is accepted
- `tests/Functional/PortableEnumFunctionalTest.php`, `tests/Functional/SchemaDiagnosticsFunctionalTest.php` and `tests/TinyintVariantsTest.php`, plus two schema-stability rows for the tinyint variants. The diagnostics suite builds raw `ENUM`, `SET`, `TINYINT` and `TINYINT UNSIGNED` columns on MySQL and MariaDB and a native enum type on PostgreSQL, then asserts both what is reported and what is not

### Changed

- CI now covers PHP 8.5
- `TinyintType`'s implementation moved to `AbstractTinyintType`, and the range decision is now an `isWithinRange(int): bool` predicate that both conversion paths consult. Until now the integer path called `validateRange()` while the string path repeated the same bounds as a literal, so a subclass that overrode `validateRange()` silently had no effect on an integer-formatted string — the two halves of one type could disagree about what its range was. They cannot now. `validateRange()` keeps its signature and still delegates, so an existing override still governs the integer path; overriding `isWithinRange()` governs both. The original string still reaches the message, because `(int)"999999999999999999999"` collapses to `PHP_INT_MAX` and reporting that instead would name a value the caller never wrote
- `IntegrationDatabase::dataProviderEngine()` is now `dataProviderMySqlEngine()`, beside a new `dataProviderPostgreSqlEngine()`. The old name did not say that every suite behind it asserts MySQL-native declarations, and adding PostgreSQL to it would have broken them. `dropTable()` quotes through the platform instead of hard-coded backticks, which is what a PostgreSQL row would have hit first
- `phpstan.neon` and `.php-cs-fixer.dist.php` now cover `bin/doctrine-type-diagnose`. The file has no `.php` extension, so both tools were skipping it silently — the one file in the package that a user executes directly was the only one nothing checked. Analysing it immediately paid for itself: on PHP 8.5 phpstan rejects the `$argv` global as possibly undefined, which would have failed the whole `test` matrix job
- `IntegrationDatabase::createTable()` drops the table it is about to create instead of a hard-coded name, which is what a second functional suite using its own table name needed
- `infection.json5` — the `minMsi` and `minCoveredMsi` floors raised from 90 to 91, the score measured with this release's tests in place. The mutants still escaping are structural: the `protected` visibility this package requires of every hook, and the two `SchemaDiagnostics::inspect()` mutants that only a real server can kill, which the mutation run excludes by design

### Deprecated

- `TinyintType` — use `SignedTinyintType` or `UnsignedTinyintType`. It still works unchanged and nothing is scheduled for removal; migrating is a type-name change on the column plus a registration line, and the emitted DDL is identical as long as the column's `unsigned` option matches the variant

## [v3.5.0] - 2026-08-17 - The generated SQL round-trips, or says why it cannot, and exceptions carry structured context

### Added

- `Contract\ExceptionInterface` and `Exception\Trait\ExceptionTrait` — exceptions now carry a structured `context` array alongside the message, read with `getContext()` and set with `setContext()` or the new fourth constructor argument. The context is purely additive: no existing message, code or previous throwable changed, so a consumer logging only `getMessage()` sees exactly what it saw before. Ported from `precision-soft/symfony-console`, which has carried it since v4.5.0, so every package in the portfolio now exposes the same contract. Note for consumers subclassing the package exception: a subclass that already declares its own `$context` property or a `getContext()`/`setContext()` method will collide with the trait. The base `Exception` keeps Doctrine's `Doctrine\DBAL\Exception` marker and now implements both it and `ExceptionInterface`, so an existing `catch (Doctrine\DBAL\Exception)` is unaffected
- `tests/Functional/SchemaStabilityFunctionalTest.php` — the first tests in this package to execute the SQL it generates. Every type is declared on a real MySQL 8.4 and MariaDB 11.4 server, the table is created, introspected and compared, and the resulting `ALTER TABLE` list is asserted. Establishes what `doctrine:schema:update` actually does with these columns: `enum` (all three flavours), `set`, unsigned `TINYINT` and `DateTimeType` with `update` never settle, while signed `TINYINT` and plain `DateTimeType` do. As a control, every DBAL built-in type was verified to round-trip cleanly in the same environment, so the behaviour belongs to this package and not to the engines
- `tests/Functional/ValueRoundTripFunctionalTest.php` — `convertToDatabaseValue()` → `INSERT` → `SELECT` → `convertToPHPValue()` against both engines: the enum column holds the backing value and the pure-enum column the case name; an int-backed enum comes back from the server as a *string*, so the round trip depends on `getEnumByValue()`'s integer-formatted-string branch; a `SET` is normalised into declaration order by the server, not the order it was written in; an empty set is stored as `NULL`; the server rejects a value outside the enum; `ON UPDATE CURRENT_TIMESTAMP` really does rewrite the column on every `UPDATE`
- `tests/Utility/IntegrationDatabase.php`, `tests/Utility/SkipIntegrationException.php` — the integration harness, deliberately DBAL-only so the package does not grow a `doctrine/orm` dev dependency. Connections are built outside the `try` that catches unreachability, so a malformed DSN fails loudly while only a missing server becomes a skip
- `composer.json` — added a `test-integration` script; `test` now excludes the `integration` group, so `composer check` stays fast and offline
- `README.md` — a *Schema Stability* section documenting why these columns do not round-trip, which ones are affected, and the `getMappedDatabaseTypes()` override a consumer can add to settle one of them, including the two costs that keep it opt-in: the claim on a database type name is global and exclusive, and owning `tinyint` also takes over every `boolean` column. `tests/Utility/MappedEnumType.php` pins that documented recipe with a test, so it cannot rot
- two assertions covering the SQL-declaration cache key's remaining components. **The key separates one concrete Type from another** — the cache is `protected static` on `AbstractPhpEnumType` and shared by every subclass, so without `static::class` in the key one enum's `ENUM(...)` is handed to a different enum entirely; the column, the platform and the key order were pinned and this was not. **And `getEnumByValue()`'s integer-formatted-string pattern is anchored at both ends** — `not_a_number` carries no digits and so is rejected either way, while `5abc` is the input that separates the two states: unanchored it matches, `(int)` truncates it to `5`, and a corrupt column value silently hydrates to a valid enum case instead of being refused
- tests for four branches that had never been executed: the `ksort()` normalisation that is the whole point of v3.4.5 (verified by removing the call, which produces two cache entries instead of one), the platform component of the same cache key, the `getEnumByName()` guard against a class constant that *is* an enum case under a different name, the empty-string half of the `AbstractSetType` filter predicate, a negative int-backed enum value through `getEnumByValue()`'s `-?` pattern, and `DateTimeType` rejecting a truthy non-boolean `update`

### Changed

- `AbstractPhpEnumType::getValues()` — the documented return type widened from `array<int, UnitEnum>` to `array<int, UnitEnum|string|int>`. The declaration described only the enum-backed half of the contract: in `notEnum` mode the only supported usage is an override returning plain scalars — which the method's own exception message instructs — and every arm of `convertValueToDatabase()`, `convertValueToPhp()` and the `(string)` cast in `buildSqlDeclaration()` is written for exactly that. Runtime behaviour is unchanged; subclasses that widened their own declaration to compensate no longer need to
- `AbstractSetType::convertToPHPValue()` — the same widening, for the same reason: a `notEnum` set hydrates to raw values, never to enum cases
- `AbstractPhpEnumType` — the comment claiming the caches are "per-class" now describes what the code does. The three static arrays are declared once and shared by every subclass; separation comes from the cache key, which carries `static::class`
- comments across the package normalized to the house rule — the default is no comment, and a warranted one is a single short line. Every multi-line rationale block, narrative test docblock and shell section header was removed; the shell scripts now carry nothing but their shebang, as in the rest of the portfolio. Nothing behavioral changed. `CONTRIBUTING.md` gained the two sections that now carry the rationale — *Development toolchain* (the pinned pcov and infection builds, the `php.dev.ini` overlay, the mutation thresholds) and *Continuous integration* (the four jobs, and why `--fail-on-skipped` is passed in CI only) — and its *Comments and messages* rules were made explicit. The *Verification* section now documents `.dev/validate/all.sh` and its flags, replacing the stale description of the old hook
- `README.md` — `TinyintType` now states that it cannot enforce the column's half of its range: `200` is valid unsigned and out of range signed, and the type accepts it either way. Only a server in strict mode refuses it

### Removed

- `phpstan-baseline.neon` — the file contained `ignoreErrors: []` and suppressed nothing. Deleted along with its `includes:` entry; the analysis is clean at level 8 without it

## [v3.4.5] - 2026-06-17 - Deterministic SQL Declaration Cache Key

### Changed

- `AbstractPhpEnumType::buildSqlDeclaration()` — the column metadata is now key-sorted before being serialized into the SQL-declaration cache key, so logically identical column arrays supplied in a different key order resolve to the same cache entry instead of missing the cache; the emitted SQL is unchanged

### Added

- `composer.json` — added `test`, `phpstan`, `cs-check`, `cs-fix` and an aggregate `check` convenience script wrapping `simple-phpunit`, `phpstan`, and `php-cs-fixer`

## [v3.4.4] - 2026-04-23 - Extend Late Static Binding to AbstractPhpEnumType Cache Properties

### Fixed

- `AbstractPhpEnumType::clearCache()`, `buildSqlDeclaration()`, `getEnumType()`, and `getEnumByValue()` — changed `self::$enumTypeCache`, `self::$backingTypeCache`, and `self::$sqlDeclarationCache` to `static::` on all 14 call sites; the v3.4.3 pass fixed `AbstractType` but missed the three `protected static` caches in `AbstractPhpEnumType`

### Changed

- `composer.json` — removed the `version` field; version is now managed exclusively via GitHub release tags

## [v3.4.3] - 2026-04-23 - AbstractType late static binding on name cache

### Fixed

- `AbstractType::getDefaultName()` — cache lookup changed from `self::$defaultNameCache` to `static::$defaultNameCache`; subclasses that redeclare the protected static property now correctly isolate their own cache slot rather than writing into the parent's shared array

## [v3.4.2] - 2026-04-21 - CHANGELOG standardization and cache visibility alignment

### Changed

- `AbstractType::$defaultNameCache` — visibility widened from `private static` to `protected static` for subclass access, aligning with the v3.2.0 widening of `$enumTypeCache` and `$backingTypeCache` on `AbstractPhpEnumType`
- `TinyintType::getDefaultName()` — returns `static::TINYINT` instead of `self::TINYINT` so subclasses can override the `TINYINT` constant without re-declaring the method (late static binding)
- `tests/Contract/AbstractPhpEnumTypeTest.php` — added `use Doctrine\DBAL\Platforms\PostgreSQLPlatform` import and replaced the inline `new \Doctrine\DBAL\Platforms\PostgreSQLPlatform()` with the short-name form
- `tests/Contract/AbstractSetTypeTest.php` — replaced inline `new \stdClass()` with the short-name form (import already present)
- `CHANGELOG.md` — every historical entry rewritten with the titled-heading format `## [vX.Y.Z] - YYYY-MM-DD - <Title>`; section order normalized to Breaking Changes → Fixed → Changed → Added → Deprecated → Removed; bullet wording aligned against the actual tag-to-tag diff

## [v3.4.1] - 2026-04-20 - Symmetric convertToPhpValue enum-class guard and cache SQL declarations

### Fixed

- `AbstractEnumType::convertToPHPValue()` — now rejects pre-hydrated `UnitEnum` values that do not belong to the configured `getEnumClass()`, throwing `InvalidTypeValueException` with the same message shape as `convertToDatabaseValue()`. Completes the symmetric guard introduced in v3.4.0: previously a mismatched enum from another class could silently pass through the "already hydrated" branch exercised by round-trip tests and virtual/computed columns
- `AbstractSetType::convertToPHPValue()` — now validates each element of a pre-hydrated array against the configured `getEnumClass()` when one is set; throws `InvalidTypeValueException` for non-enum elements (`expected enum case of ...`) and for enum cases from foreign classes (`does not belong to ...`). Untyped sets (no `getEnumClass()`) continue to pass arrays through unchanged

### Changed

- `AbstractPhpEnumType::buildSqlDeclaration()` — result is now cached so repeated `getSQLDeclaration()` calls during schema operations no longer re-walk the enum cases or re-resolve the platform branch. Cache key combines `static::class`, the SQL keyword (`ENUM` vs `SET`), the platform class, and `serialize($column)` so different column shapes (e.g. `length=64` vs `length=255` on non-MySQL) do not share a slot
- `phpstan.neon` — removed the `bootstrapFiles` directive that pointed at the `symfony/phpunit-bridge` download under `vendor/bin/.phpunit/`. PHPStan now resolves all classes through the project's own Composer autoload, so the configuration no longer depends on an external tool's on-disk layout

### Added

- `AbstractPhpEnumType::$sqlDeclarationCache` — `protected static array<string, string>` holding cached SQL declarations; cleared alongside the existing caches by `clearCache()`
- `tests/Contract/AbstractEnumTypeTest.php` — `testConvertToPhpValueWrongEnumClassThrows`, `testConvertToPhpValuePassesMatchingEnumThrough`
- `tests/Contract/AbstractPhpEnumTypeTest.php` — `testBuildSqlDeclarationCacheReturnsIdenticalResult`, `testBuildSqlDeclarationCacheDistinguishesColumnArguments`
- `tests/Contract/AbstractSetTypeTest.php` — `testConvertToPhpValueHydratedArrayWithWrongEnumClassThrows`, `testConvertToPhpValueHydratedArrayWithNonEnumElementThrows`, `testConvertToPhpValueHydratedArrayUntypedSetPassesThrough`

## [v3.4.0] - 2026-04-16 - Enum class validation and shared SQL declaration logic

### Breaking Changes

- `AbstractEnumType::convertToDatabaseValue()` — validates that the passed enum belongs to the configured `getEnumClass()`, throwing `InvalidTypeValueException` when a mismatched enum is passed (previously any `UnitEnum`/`BackedEnum` was silently accepted regardless of class); callers that relied on cross-class enum values being silently accepted will now receive an exception
- `TinyintType::getSQLDeclaration()` — throws base `Exception` instead of `InvalidTypeValueException` for unsupported platforms; the unsupported-platform error is a configuration issue, not a value error; callers catching `InvalidTypeValueException` specifically will no longer catch this case

### Changed

- `AbstractPhpEnumType::buildSqlDeclaration()` — shared protected helper extracted from `AbstractEnumType::getSQLDeclaration()` and `AbstractSetType::getSQLDeclaration()`; both now delegate instead of duplicating the quoted-values + platform-check + non-MySQL fallback logic
- `TinyintType::parseIntValue()` — visibility widened from `private` to `protected` for subclass extensibility
- Composer version constraints standardized to caret notation: `doctrine/dbal: ^4.0`, `friendsofphp/php-cs-fixer: ^3.0`
- PHPDoc simplified: `UnitEnum|BackedEnum` → `UnitEnum` (redundant since `BackedEnum extends UnitEnum`)
- All test classes annotated with `final` and `@internal` per project convention
- All test assertions changed from `self::assert*()` to `static::assert*()`
- `README.md` — documented `TinyintType::getSQLDeclaration()` exception type corrected to base `Exception`

### Added

- `AbstractEnumType` wrong-enum-class test coverage
- `tests/ExceptionTest.php` — asserts that `PrecisionSoft\Doctrine\Type\Exception\Exception` implements `Doctrine\DBAL\Exception`
- SQLite platform test coverage for ENUM and SET non-MySQL fallback

## [v3.3.0] - 2026-04-16 - MariaDB compatibility and enum and tinyint validation hardening

### Fixed

- MariaDB compatibility — `DateTimeType`, `TinyintType`, `AbstractEnumType`, and `AbstractSetType` now check `AbstractMySQLPlatform` instead of `MySQLPlatform`, so MariaDB connections correctly generate MySQL-flavored SQL (`ENUM(...)`, `SET(...)`, `TINYINT`, `ON UPDATE CURRENT_TIMESTAMP`) instead of the non-MySQL fallback
- `AbstractEnumType::getSQLDeclaration()` and `AbstractSetType::getSQLDeclaration()` — non-MySQL fallback now injects `$column['length'] ??= 255` and `$column['name'] ??= ''` so `AbstractPlatform::getStringTypeDeclarationSQL()` receives the keys it requires (PostgreSQL and others previously produced invalid SQL or threw when the caller omitted these)
- `AbstractPhpEnumType::getEnumByName()` — the resolved value is now verified to be a `UnitEnum` whose `->name` matches the requested case name, rejecting class constants (`const FOO = ...`) that share a name with a would-be case and rejecting inherited / case-mismatched constants
- `AbstractPhpEnumType::getEnumByName()` — validates the input is a string before looking up the constant, throwing `InvalidTypeValueException` with a clear message (previously non-string inputs silently stringified into invalid lookups)
- `AbstractPhpEnumType::getEnumByValue()` — validates the input matches the backing type (int for int-backed enums, string for string-backed) before calling `tryFrom()`, replacing opaque `TypeError` from the Doctrine runtime with a typed `InvalidTypeValueException`
- `TinyintType` — out-of-range error now reports the original string value (e.g. `99999999999999999999`) instead of the silently-truncated `PHP_INT_MAX`, avoiding a misleading error message
- `AbstractSetType::convertToDatabaseValue()` — typed enum sets (with a configured enum class) now throw `InvalidTypeValueException` on null elements instead of silently filtering them; untyped sets retain the filtering behavior for backward compatibility

### Changed

- `AbstractEnumType::convertToPHPValue()` — passes an already-hydrated `UnitEnum` instance through without reprocessing (relevant for round-trip tests and virtual/computed columns)
- `AbstractPhpEnumType::getValues()`, `getEnumType()`, `getEnumByName()`, `getEnumByValue()` — `@throws` PHPDoc annotations added
- `AbstractSetType::convertToPHPValue()` — passes an already-hydrated array through without reprocessing
- `PrecisionSoft\Doctrine\Type\Exception\Exception` now implements `Doctrine\DBAL\Exception` so consumers can catch all DBAL-flavored errors with a single interface
- `TinyintType` — regex now accepts a leading `+` sign (`/^[+-]?\d+$/`) so `'+42'` is parsed the same as `'42'`
- `TinyintType::throwOutOfRangeException()` signature widened from `int` to `int|string`
- `AbstractType::getDefaultName()` — caches the Reflection-derived short name per `static::class` to avoid repeated `ReflectionClass` instantiation during type resolution
- `AbstractSetType` — inline documentation clarifying null return for empty sets and `array_unique` loose comparison behaviour
- `AbstractSetType::convertToPHPValue()` — documented the defensive `trim()` on comma-split values
- `DateTimeType::getSQLDeclaration()` — documented the truthy `$column['update']` tolerance
- `PrecisionSoft\Doctrine\Type\Enum\EnumType` — documented the asymmetry between `notEnum` (pass-through) and `simple` / `backed` (resolve + validate)
- `AbstractPhpEnumType::clearCache()` — documented that the method is not thread-safe and is intended for test teardown or CLI warm-up
- `README.md` — corrected `TinyintType::getSQLDeclaration()` documented exception type to `InvalidTypeValueException`; added note that the method requires a MySQL platform

### Added

- MariaDB platform test coverage for ENUM and SET types
- Int-backed enum round-trip coverage against `AbstractSetType` (new `TestIntBackedSetType` fixture)
- Int-backed enum `getSQLDeclaration` test coverage verifying numeric case values are quoted as strings (avoids MySQL `ENUM(1,5,10)` positional-reference confusion)
- Strengthened non-MySQL fallback assertions in `AbstractEnumTypeTest` and `AbstractSetTypeTest` to verify `VARCHAR(255)`
- `getEnumByName()` non-string input and class-constant test coverage
- `getEnumByValue()` non-matching backing-type test coverage
- `TinyintType` `'+42'` acceptance and large-string error-message test coverage
- `AbstractSetType` null-element-in-typed-enum-set test coverage and already-hydrated array pass-through test coverage

### Removed

- `AbstractType::requiresSQLCommentHint()` — dead override; DBAL 4 removed this method from `Doctrine\DBAL\Types\Type`

## [v3.2.1] - 2026-04-13 - convertToPHPValue range validation and out-of-range helper

### Fixed

- `TinyintType::convertToPHPValue()` — now calls `validateRange()` for both `int` and integer-formatted `string` inputs, rejecting out-of-range values with `InvalidTypeValueException` (previously only `convertToDatabaseValue()` enforced the range)

### Changed

- `TinyintType` — extracted out-of-range error into the protected `throwOutOfRangeException(int $value): never` helper for reuse and subclass overrides

## [v3.2.0] - 2026-04-13 - Widen cache and lookup visibility and replace Error catch with defined

### Changed

- `AbstractPhpEnumType::$enumTypeCache` and `$backingTypeCache` — visibility widened from `private static` to `protected static` to allow subclass access
- `AbstractPhpEnumType::getEnumType()`, `getEnumByName()`, `getEnumByValue()` — visibility widened from `private` to `protected`
- `AbstractPhpEnumType::getEnumByName()` — replaced `try/catch Error` with `\defined()` check before `\constant()` call; removed the now-unused `use Error` import
- `AbstractPhpEnumType::getValues()` — PHPDoc return type narrowed from `array<int, mixed>` to `array<int, UnitEnum>`
- `TinyintType::validateRange()` — visibility widened from `private` to `protected`

### Added

- `AbstractSetTypeTest` — `convertToPHPValue` tests for non-string inputs (int, array) and whitespace-padded set values
- `TinyintTypeTest` — `convertToPHPValue` tests for invalid types (float, bool, array, object, non-numeric string)

## [v3.1.2] - 2026-04-10 - convertToPHPValue strict integer check and set null guard refactor

### Fixed

- `TinyintType::convertToPHPValue()` — validates that the input is an `int` or an integer-formatted string; throws `InvalidTypeValueException` for any other type

### Changed

- `AbstractSetType::convertToDatabaseValue()` — refactored the null guard to an early return for clarity

## [v3.1.1] - 2026-04-09 - TinyintType unsupported platform error includes platform class

### Fixed

- `TinyintType::getSQLDeclaration()` — includes the actual platform class name in the unsupported-platform error message

## [v3.1.0] - 2026-04-07 - Backing-type cache and SET element validation

### Fixed

- `AbstractPhpEnumType::getEnumByName()` — return type narrowed from `mixed` to `UnitEnum`
- `AbstractPhpEnumType::getEnumByValue()` — return type narrowed from `mixed` to `BackedEnum`; the redundant `null === $enumClassName` guard was removed (the case is already rejected by the `EnumType::notEnum` branch upstream)
- `AbstractSetType::convertToDatabaseValue()` — filters out empty strings in addition to `null` values so an empty-string `convertValueToDatabase()` result no longer produces a stray `,,` segment in the joined payload

### Changed

- `AbstractPhpEnumType::getEnumByName()` — reverted the `::cases()` iteration to `\constant($enumClassName . '::' . $enumCaseName)` with `catch (Error)`, trading per-call iteration for a single constant lookup
- `AbstractPhpEnumType::getEnumByValue()` — backing-type `ReflectionEnum` lookup is now cached per enum class via a static map, so int-backed enum resolves no longer re-reflect on every call
- `AbstractEnumType::getSQLDeclaration()` and `AbstractSetType::getSQLDeclaration()` — cast the `convertValueToDatabase()` result to `(string)` before `quoteStringLiteral()` so int-backed enum case values are quoted correctly in the generated `ENUM(...)` / `SET(...)` SQL

### Added

- `AbstractPhpEnumType::$backingTypeCache` — static `array<class-string, ?string>` caching the backing type per enum class; cleared alongside `$enumTypeCache` by `clearCache()`
- `AbstractSetType::convertToDatabaseValue()` — validates each element of a typed set is an instance of the configured `getEnumClass()`, throwing `InvalidTypeValueException` either as `expected enum case of ...` for non-`UnitEnum` elements or as `enum case ... does not belong to ...` for enum cases from foreign classes
- `AbstractSetType::convertToPHPValue()` — throws `InvalidTypeValueException` when the raw database value is not a string
- Int-backed enum test fixtures and coverage (`TestIntBackedEnum`, `TestIntBackedEnumType`)

## [v3.0.2] - 2026-04-06 - Int-backed enum support and built-in function prefixing

### Fixed

- `AbstractPhpEnumType::getEnumByValue()` — int-backed enums now resolve correctly: the database value is cast to `int` via `ReflectionEnum::getBackingType()` detection before `BackedEnum::tryFrom()` (previously the string-typed database value failed the strict-typed `tryFrom()` call on int-backed enums)
- `AbstractSetType::convertToPHPValue()` — comma-split database values are passed through `\trim()` before `convertValueToPhp()`, so whitespace-padded segments no longer fail enum resolution

### Changed

- `AbstractPhpEnumType::getEnumByName()` — refactored from immediate `return $enumCase` inside the `::cases()` loop to a break+result pattern with Yoda comparison (`$enumCaseName === $enumCase->name`)
- `AbstractPhpEnumType::getEnumValues()` — removed the redundant `null === $enumClassName` guard (already caught by the `EnumType::notEnum` branch); replaced with a `@var class-string<UnitEnum>` annotation
- Built-in function calls across `AbstractPhpEnumType`, `AbstractSetType`, `AbstractEnumType`, and `TinyintType` now use the `\` global-namespace prefix for consistency

## [v3.0.1] - 2026-04-04 - PHPUnit 11.5 upgrade and PHPStan test coverage expansion

### Changed

- Upgraded from PHPUnit 9 to PHPUnit 11.5 via `precision-soft/symfony-phpunit: ^3.0`
- Replaced `<coverage processUncoveredFiles="true">` with the `<source>` element in `phpunit.xml.dist`
- Replaced `<listeners>` with `<extensions>` using `Symfony\Bridge\PhpUnit\SymfonyExtension`
- Added `failOnRisky` and `failOnWarning` attributes to `phpunit.xml.dist`
- Added `tests/` to PHPStan analysis paths in `phpstan.neon`
- Added PHPStan type annotations to anonymous test classes in `AbstractPhpEnumTypeTest`
- Uppercased `TINYINT` SQL keyword in `TinyintType::getSQLDeclaration()` and the `validateRange()` error message
- Quoted `$COMPOSER_DEV_MODE` variable in the `composer.json` hook script

## [v3.0.0] - 2026-04-03 - Require DBAL 4 stricter type contracts and raised quality gates

### Breaking Changes

- `doctrine/dbal` constraint narrowed from `3.*||4.*` to `4.*` — Doctrine DBAL 3 is no longer supported
- `AbstractPhpEnumType::convertValueToDatabase()` — visibility narrowed from `public` to `protected`
- `AbstractPhpEnumType::convertValueToPhp()` — visibility narrowed from `public` to `protected`
- `TinyintType::convertToDatabaseValue()` — return type narrowed from `int|string|null` to `?int` (numeric strings now return `int`)
- `AbstractSetType::convertToDatabaseValue()` — now throws `InvalidTypeValueException` when passed a non-array value (previously silently cast to array)
- `DateTimeType` — column option `update` now requires strict `true` (previously accepted any truthy value)
- `TinyintType::getName()` — removed (DBAL 3 compatibility shim)
- `squizlabs/php_codesniffer` dev dependency and `phpcs.xml` removed
- `phpunit.xml` renamed to `phpunit.xml.dist`
- Dev directory renamed from `dev/` to `.dev/`

### Fixed

- `AbstractEnumType::getSQLDeclaration()` — non-MySQL fallback uses `getStringTypeDeclarationSQL()` instead of `getIntegerTypeDeclarationSQL()`
- `AbstractSetType::getSQLDeclaration()` — same fix as above
- `TinyintType::getSQLDeclaration()` — throws `InvalidTypeValueException` instead of base `Exception` on non-MySQL platforms
- `TinyintType::validateRange()` — uses Yoda conditions

### Changed

- `precision-soft/symfony-phpunit` bumped from `1.*` to `2.*`; `phpstan/phpstan: ^2.0` added as a dev dependency
- All test classes now extend `AbstractTestCase` from `precision-soft/symfony-phpunit`
- Replaced `strpos()` with `str_contains()` in `AbstractSetType`
- Replaced `class_exists()` + `enum_exists()` with `enum_exists()` only in `AbstractPhpEnumType::getEnumType()`
- `TinyintType::validateRange()` — removed the unused `$unsigned` parameter
- Exception messages improved with context
- Descriptive variable names across all source and test files (no generic `$value`, `$result`)
- Redundant PHPDoc comments removed
- `homepage` in `composer.json` repointed from `gitlab.com/precision-soft-open-source/doctrine/type` to `github.com/precision-soft/doctrine-type`
- Docker `entrypoint.sh` for the dev container replaces `dev/docker/setup.sh`
- README — type hierarchy, cache semantics, security and troubleshooting sections added

### Added

- `AbstractType::requiresSQLCommentHint()` returns `true` so Doctrine schema tools detect custom type changes
- `AbstractSetType::convertToDatabaseValue()` — deduplicates values with `array_unique()`
- `AbstractSetType::convertToPHPValue()` — `@return` PHPDoc with typed array
- Null guards in `AbstractPhpEnumType` for `::cases()` and `::tryFrom()` calls
- PHPStan level 8 configuration with an empty baseline (zero ignored errors)
- `AbstractTypeTest`, `EnumTypeTest`, `ExceptionTest` test classes
- `TestConcreteType`, `TestPrefixedType` test utilities

### Removed

- `TinyintType::getName()` — DBAL 3 compatibility shim
- `squizlabs/php_codesniffer` dev dependency and the `phpcs.xml` configuration file
- `dev/docker/setup.sh` — replaced by `.dev/docker/entrypoint.sh`

## [v2.2.3] - 2026-03-20 - Filter null set values and correct README clone URL

### Fixed

- `AbstractSetType::convertToDatabaseValue()` — filters out null values in the converted array to prevent empty comma-delimited segments being serialized

### Changed

- `README.md` — clone URL corrected from the legacy GitLab host to `github.com/precision-soft/doctrine-type`

### Added

- `tests/Contract/AbstractSetTypeTest.php` — coverage for null-element filtering on the database-bound path

## [v2.2.2] - 2026-03-19 - TinyintType range validation and set comma check with PHPUnit coverage

### Fixed

- `TinyintType::convertToDatabaseValue()` — validates the result fits the TINYINT signed+unsigned range (`-128..255`) and throws `InvalidTypeValueException` otherwise
- `AbstractSetType::convertToDatabaseValue()` — rejects values containing a comma (which would corrupt the `,`-joined SET payload) with `InvalidTypeValueException`

### Added

- Full PHPUnit test suite bootstrapped: `tests/Contract/AbstractEnumTypeTest.php`, `AbstractPhpEnumTypeTest.php`, `AbstractSetTypeTest.php`, plus `tests/DateTimeTypeTest.php` and `tests/TinyintTypeTest.php`
- `tests/Utility/` — `TestBackedEnum`, `TestBackedEnumType`, `TestBackedSetType`, `TestSimpleEnum`, `TestSimpleEnumType`, `TestSimpleSetType` fixtures supporting the new tests

### Changed

- `README.md` — removed the "unit tests" TODO item now that the suite exists

## [v2.2.1] - 2026-03-19 - Correct getSQLDeclaration casing and replace empty () with explicit checks

### Fixed

- `AbstractEnumType::getSqlDeclaration()` and `AbstractSetType::getSqlDeclaration()` — method name corrected to `getSQLDeclaration()` (matches Doctrine `AbstractPlatform` casing) so the override actually dispatches

### Changed

- `AbstractSetType::convertToDatabaseValue()` — replaced `true === empty($converted)` with `0 === count($converted)` for explicitness
- `AbstractSetType::convertToPHPValue()` — replaced `true === empty($value)` with explicit `null === $value || '' === $value` checks
- `DateTimeType::getSQLDeclaration()` — replaced `false === empty($column['update'])` with explicit `isset` + non-null/non-empty-string comparison

## [v2.2.0] - 2026-03-19 - EnumType classification and AbstractPhpEnumType static cache

### Fixed

- `AbstractPhpEnumType::convertValueToDatabase()` — collapsed the switch/break fallthrough into a `match` with inline `throw` expressions, preserving the `InvalidTypeValueException` for non-matching branches
- `AbstractPhpEnumType::getEnumByValue()` — uses `::tryFrom()` instead of iterating `::cases()`

### Changed

- `AbstractEnumType::convertToDatabaseValue()` — return type widened from `?string` to `mixed` to accommodate backed-enum values of any scalar type
- `AbstractPhpEnumType` — per-instance `?EnumType $enumType` replaced with a static `array<string, EnumType> $enumTypeCache` keyed by `static::class`
- `TinyintType` — refactored to extend `AbstractType`, gaining `getDefaultName()` integration; inline return-type hints for `getSQLDeclaration()`/`convertToDatabaseValue()`/`convertToPHPValue()`/`getBindingType()` tightened
- `phpcs.xml` — removed dangling references to non-existent `bin/`, `config/`, `public/` paths, keeping only `src/` and `tests/`

## [v2.1.0] - 2026-03-13 - Code style normalization across source files

### Changed

- Whole-repo pass with `.php-cs-fixer.dist.php` — PSR-12 and risky rules applied uniformly
- `README.md` — markdown formatting tightened
- Dev git-hooks reformatted alongside the rest of the repo

## [v2.0.2] - 2024-11-24 - convertValueToDatabase rejects non-enum values with explicit exception

### Fixed

- `AbstractPhpEnumType::convertValueToDatabase()` — non-`UnitEnum` / non-`BackedEnum` inputs for `EnumType::simple` / `EnumType::backed` now throw `InvalidTypeValueException` instead of producing a `TypeError` when accessing `->name` / `->value`

## [v2.0.1] - 2024-10-17 - TinyintType getBindingType return type aligned with DBAL 4

### Fixed

- `TinyintType::getBindingType()` — return type restored from `int` to `ParameterType`, matching the DBAL 4 `Type::getBindingType()` signature

## [v2.0.0] - 2024-10-17 - Doctrine DBAL 4 support

### Breaking Changes

- `AbstractType::getName()` — removed; consumers must rely on `getDefaultName()` (introduced in v1.1.0) for type-name resolution

### Changed

- `doctrine/dbal` constraint widened from `3.*` to `3.*||4.*` so the package can be installed alongside DBAL 4

## [v1.1.0] - 2024-10-16 - AbstractPhpEnumType PHP enum hierarchy

### Added

- `AbstractPhpEnumType` — new base class between `AbstractType` and `AbstractEnumType` / `AbstractSetType`; encapsulates `getEnumClass()`, `getEnumType()` classification, `convertValueToDatabase()` / `convertValueToPhp()` helpers, and the `getEnumByName()` / `getEnumByValue()` case lookups for PHP `UnitEnum` / `BackedEnum` support
- `PrecisionSoft\Doctrine\Type\Enum\EnumType` — three-case backing enum (`notEnum`, `simple`, `backed`) used to classify each concrete Type
- `AbstractType::getDefaultName()` and `AbstractType::getDefaultNamePrefix()` — static methods that derive the Doctrine type name from the class short name, with an optional prefix for multi-database setups

### Changed

- `AbstractEnumType` and `AbstractSetType` — refactored to extend `AbstractPhpEnumType`; string-only conversion logic replaced by shared enum-aware helpers
- Whole-repo pass with `.php-cs-fixer.dist.php` — style normalized across source files

## [v1.0.1] - 2024-09-26 - TinyintType getBindingType DBAL 3 compatibility and cast-space normalization

### Fixed

- `TinyintType::getBindingType()` — return type changed from `ParameterType` to `int` to match the DBAL 3 `Type::getBindingType()` signature (DBAL 3's `ParameterType` is a class of integer constants, not a type)

### Changed

- `.php-cs-fixer.dist.php` — added `cast_spaces: { space: none }` rule, normalizing cast-space formatting (`(string) $value` → `(string)$value`, `(int) $value` → `(int)$value`) across `AbstractEnumType`, `AbstractSetType`, and `TinyintType`
- `dev/docker/Dockerfile` — added `git` to the `apk add` list so the dev container can run repo-aware tooling

## [v1.0.0] - 2024-09-17 - Initial release

### Added

- `AbstractType` — base class extending `Doctrine\DBAL\Types\Type` with sensible defaults for the MySQL-focused custom types shipped by this library
- `AbstractEnumType` — base class for MySQL `ENUM` columns; subclasses declare the allowed values via `getValues()`, and the class provides `convertToDatabaseValue()`, `convertToPHPValue()`, and `getSqlDeclaration()`
- `AbstractSetType` — base class for MySQL `SET` columns with the matching conversion and declaration methods and array <-> comma-string serialization
- `DateTimeType` — extends Doctrine's `DateTimeType` and appends `ON UPDATE CURRENT_TIMESTAMP` to the SQL declaration when the column option `update` is truthy on a MySQL platform
- `TinyintType` — custom type for MySQL `TINYINT` columns with signed/unsigned SQL declaration, `(int)` value conversion, and `ParameterType::INTEGER` binding type
- `PrecisionSoft\Doctrine\Type\Exception\Exception` and `InvalidTypeValueException` — project-specific exception hierarchy rooted at a base exception
- Docker dev container (`dev/docker/`), git pre-commit hook, php-cs-fixer / PHP_CodeSniffer / PHPUnit scaffolding

[Unreleased]: https://github.com/precision-soft/doctrine-type/compare/v3.7.0...HEAD

[v3.7.0]: https://github.com/precision-soft/doctrine-type/compare/v3.6.0...v3.7.0

[v3.6.0]: https://github.com/precision-soft/doctrine-type/compare/v3.5.0...v3.6.0

[v3.5.0]: https://github.com/precision-soft/doctrine-type/compare/v3.4.5...v3.5.0

[v3.4.5]: https://github.com/precision-soft/doctrine-type/compare/v3.4.4...v3.4.5

[v3.4.4]: https://github.com/precision-soft/doctrine-type/compare/v3.4.3...v3.4.4

[v3.4.3]: https://github.com/precision-soft/doctrine-type/compare/v3.4.2...v3.4.3

[v3.4.2]: https://github.com/precision-soft/doctrine-type/compare/v3.4.1...v3.4.2

[v3.4.1]: https://github.com/precision-soft/doctrine-type/compare/v3.4.0...v3.4.1

[v3.4.0]: https://github.com/precision-soft/doctrine-type/compare/v3.3.0...v3.4.0

[v3.3.0]: https://github.com/precision-soft/doctrine-type/compare/v3.2.1...v3.3.0

[v3.2.1]: https://github.com/precision-soft/doctrine-type/compare/v3.2.0...v3.2.1

[v3.2.0]: https://github.com/precision-soft/doctrine-type/compare/v3.1.2...v3.2.0

[v3.1.2]: https://github.com/precision-soft/doctrine-type/compare/v3.1.1...v3.1.2

[v3.1.1]: https://github.com/precision-soft/doctrine-type/compare/v3.1.0...v3.1.1

[v3.1.0]: https://github.com/precision-soft/doctrine-type/compare/v3.0.2...v3.1.0

[v3.0.2]: https://github.com/precision-soft/doctrine-type/compare/v3.0.1...v3.0.2

[v3.0.1]: https://github.com/precision-soft/doctrine-type/compare/v3.0.0...v3.0.1

[v3.0.0]: https://github.com/precision-soft/doctrine-type/compare/v2.2.3...v3.0.0

[v2.2.3]: https://github.com/precision-soft/doctrine-type/compare/v2.2.2...v2.2.3

[v2.2.2]: https://github.com/precision-soft/doctrine-type/compare/v2.2.1...v2.2.2

[v2.2.1]: https://github.com/precision-soft/doctrine-type/compare/v2.2.0...v2.2.1

[v2.2.0]: https://github.com/precision-soft/doctrine-type/compare/v2.1.0...v2.2.0

[v2.1.0]: https://github.com/precision-soft/doctrine-type/compare/v2.0.2...v2.1.0

[v2.0.2]: https://github.com/precision-soft/doctrine-type/compare/v2.0.1...v2.0.2

[v2.0.1]: https://github.com/precision-soft/doctrine-type/compare/v2.0.0...v2.0.1

[v2.0.0]: https://github.com/precision-soft/doctrine-type/compare/v1.1.0...v2.0.0

[v1.1.0]: https://github.com/precision-soft/doctrine-type/compare/v1.0.1...v1.1.0

[v1.0.1]: https://github.com/precision-soft/doctrine-type/compare/v1.0.0...v1.0.1

[v1.0.0]: https://github.com/precision-soft/doctrine-type/releases/tag/v1.0.0
