# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v3.4.1] - 2026-04-20 - Symmetric convertToPhpValue enum-class guard and cache SQL declarations

### Fixed

- `AbstractEnumType::convertToPHPValue()` — now rejects pre-hydrated `UnitEnum` values that do not belong to the configured `getEnumClass()`, throwing `InvalidTypeValueException` with the same message shape as `convertToDatabaseValue()`. Completes the symmetric guard introduced in v3.4.0 — previously a mismatched enum from another class could silently pass through the "already hydrated" branch (round-trip tests and virtual/computed columns)
- `AbstractSetType::convertToPHPValue()` — now validates each element of a pre-hydrated array against the configured `getEnumClass()` when one is set; throws `InvalidTypeValueException` for non-enum elements (`expected enum case of ...`) and for enum cases from foreign classes (`does not belong to ...`). Untyped sets (no `getEnumClass()`) continue to pass arrays through unchanged

### Changed

- `AbstractPhpEnumType::buildSqlDeclaration()` — result is now cached so repeated `getSQLDeclaration()` calls during schema operations no longer re-walk the enum cases or re-resolve the platform branch. Cache key combines `static::class`, the SQL keyword (`ENUM` vs `SET`), the platform class, and `serialize($column)` so different column shapes (e.g. `length=64` vs `length=255` on non-MySQL) do not share a slot
- `phpstan.neon` — removed the `bootstrapFiles` directive that pointed at the `symfony/phpunit-bridge` download under `vendor/bin/.phpunit/`. PHPStan now resolves all classes through the project's own Composer autoload, so the configuration no longer depends on an external tool's on-disk layout (DT-09)

### Added

- `AbstractPhpEnumType::$sqlDeclarationCache` — `protected static array<string, string>` holding cached SQL declarations; cleared alongside the existing caches by `clearCache()`
- `tests/Contract/AbstractEnumTypeTest.php` — `testConvertToPhpValueWrongEnumClassThrows`, `testConvertToPhpValuePassesMatchingEnumThrough`
- `tests/Contract/AbstractPhpEnumTypeTest.php` — `testBuildSqlDeclarationCacheReturnsIdenticalResult`, `testBuildSqlDeclarationCacheDistinguishesColumnArguments` (verifies the cache key includes the column array so different `length` values produce different SQL)
- `tests/Contract/AbstractSetTypeTest.php` — `testConvertToPhpValueHydratedArrayWithWrongEnumClassThrows`, `testConvertToPhpValueHydratedArrayWithNonEnumElementThrows`, `testConvertToPhpValueHydratedArrayUntypedSetPassesThrough`

## [v3.4.0] - 2026-04-16

### Breaking Changes

- `AbstractEnumType::convertToDatabaseValue()` — validates that the passed enum belongs to the configured `getEnumClass()`, throwing `InvalidTypeValueException` when a mismatched enum is passed (previously any `UnitEnum`/`BackedEnum` was silently accepted regardless of class); callers that relied on cross-class enum values being silently accepted will now receive an exception
- `TinyintType::getSQLDeclaration()` — throws base `Exception` instead of `InvalidTypeValueException` for unsupported platforms; the unsupported-platform error is a configuration issue, not a value error; callers catching `InvalidTypeValueException` specifically will no longer catch this case

### Changed

- `getSQLDeclaration()` shared logic extracted into `AbstractPhpEnumType::buildSqlDeclaration()` — `AbstractEnumType` and `AbstractSetType` now delegate instead of duplicating the quoted-values + platform-check + fallback logic
- `TinyintType::parseIntValue()` visibility widened from `private` to `protected` for subclass extensibility
- Composer version constraints standardized to caret notation: `doctrine/dbal: ^4.0`, `friendsofphp/php-cs-fixer: ^3.0`
- PHPDoc simplified: `UnitEnum|BackedEnum` → `UnitEnum` (redundant since `BackedEnum extends UnitEnum`)
- All test classes annotated with `final` and `@internal` per project convention
- All test assertions changed from `self::assert*()` to `static::assert*()`
- `README.md` — corrected `TinyintType::getSQLDeclaration()` documented exception type to `Exception`

### Added

- `AbstractEnumType` wrong-enum-class test coverage
- `ExceptionTest` — `DoctrineDbalException` interface assertion
- SQLite platform test coverage for ENUM and SET fallback

## [v3.3.0] - 2026-04-16

### Fixed

- MariaDB compatibility — `DateTimeType`, `TinyintType`, `AbstractEnumType`, and `AbstractSetType` now check `AbstractMySQLPlatform` instead of `MySQLPlatform`, so MariaDB connections correctly generate MySQL-flavored SQL (`ENUM(...)`, `SET(...)`, `TINYINT`, `ON UPDATE CURRENT_TIMESTAMP`) instead of the non-MySQL fallback
- `AbstractEnumType::getSQLDeclaration()` and `AbstractSetType::getSQLDeclaration()` — non-MySQL fallback now injects `$column['length'] ??= 255` and `$column['name'] ??= ''` so `AbstractPlatform::getStringTypeDeclarationSQL()` receives the keys it requires (PostgreSQL and others previously produced invalid SQL or threw when the caller omitted these)
- `AbstractPhpEnumType::getEnumByName()` — the resolved value is now verified to be a `UnitEnum` whose `->name` matches the requested case name, rejecting class constants (`const FOO = ...`) that share a name with a would-be case and rejecting inherited / case-mismatched constants
- `AbstractPhpEnumType::getEnumByName()` — validates the input is a string before looking up the constant, throwing `InvalidTypeValueException` with a clear message (previously non-string inputs silently stringified into invalid lookups)
- `AbstractPhpEnumType::getEnumByValue()` — validates the input matches the backing type (int for int-backed enums, string for string-backed) before calling `tryFrom()`, replacing opaque `TypeError` from Doctrine runtime with a typed `InvalidTypeValueException`
- `TinyintType` — out-of-range error now reports the original string value (e.g. `99999999999999999999`) instead of the silently-truncated `PHP_INT_MAX`, avoiding a misleading error message
- `AbstractSetType::convertToDatabaseValue()` — typed enum sets (with a configured enum class) now throw `InvalidTypeValueException` on null elements instead of silently filtering them; untyped sets retain the filtering behavior for backward compatibility
- `AbstractPhpEnumType::getValues()`, `getEnumType()`, `getEnumByName()`, `getEnumByValue()` — `@throws` annotations added

### Changed

- `AbstractEnumType::convertToPHPValue()` — passes an already-hydrated `UnitEnum` instance through without reprocessing (relevant for round-trip tests and virtual/computed columns)
- `AbstractSetType::convertToPHPValue()` — passes an already-hydrated array through without reprocessing
- `PrecisionSoft\Doctrine\Type\Exception\Exception` now implements `Doctrine\DBAL\Exception` so consumers can catch all DBAL-flavored errors with a single interface
- `TinyintType` — regex now accepts a leading `+` sign (`/^[+-]?\d+$/`) so `'+42'` is parsed the same as `'42'`
- `TinyintType::throwOutOfRangeException()` signature widened from `int` to `int|string`
- `AbstractType::getDefaultName()` — caches the Reflection-derived short name per `static::class` to avoid repeated `ReflectionClass` instantiation during type resolution
- `AbstractSetType` — inline documentation clarifying null return for empty sets and `array_unique` loose comparison behaviour
- `AbstractSetType::convertToPHPValue()` — documented the defensive `trim()` on comma-split values
- `DateTimeType::getSQLDeclaration()` — documented the truthy `$column['update']` tolerance
- `PrecisionSoft\Doctrine\Type\Enum\EnumType` — documented the asymmetry between `notEnum` (pass-through) and `simple` / `backed` (resolve + validate)
- `AbstractPhpEnumType::clearCache()` — documented the method is not thread-safe and is intended for test teardown or CLI warm-up
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

## [v3.2.1] - 2026-04-13

### Fixed

- `TinyintType::convertToPHPValue()` — now calls `validateRange()` for both `int` and integer-formatted `string` inputs, rejecting out-of-range values with `InvalidTypeValueException` (previously only `convertToDatabaseValue()` enforced the range)

### Changed

- `TinyintType` — extracted out-of-range error into protected `throwOutOfRangeException(int $value): never` helper for reuse and subclass overrides

## [v3.2.0] - 2026-04-13

### Added

- `AbstractSetTypeTest` — `convertToPHPValue` tests for non-string types (int, array) and whitespace-padded set values
- `TinyintTypeTest` — `convertToPHPValue` tests for invalid types (float, bool, array, object, non-numeric string)

### Changed

- `AbstractPhpEnumType` — `$enumTypeCache` and `$backingTypeCache` visibility widened from `private static` to `protected static` to allow subclass access
- `AbstractPhpEnumType` — `getEnumType()`, `getEnumByName()`, `getEnumByValue()` visibility widened from `private` to `protected`
- `AbstractPhpEnumType::getEnumByName()` — replaced `try/catch Error` with `\defined()` check before `\constant()` call; removed unused `use Error` import
- `AbstractPhpEnumType::getValues()` — PHPDoc return type narrowed from `array<int, mixed>` to `array<int, UnitEnum>`
- `TinyintType` — `validateRange()` visibility widened from `private` to `protected`

## [v3.1.2] - 2026-04-10

### Fixed

- `TinyintType::convertToPHPValue()` — validates that the input is an `int` or an integer-formatted string; throws `InvalidTypeValueException` for any other type

### Changed

- `AbstractSetType::convertToDatabaseValue()` — refactored null guard to early return for clarity

## [v3.1.1] - 2026-04-09

### Fixed

- `TinyintType::getSQLDeclaration()` — includes actual platform class name in unsupported platform error message

## [v3.1.0] - 2026-04-07

### Added

- `AbstractPhpEnumType` — int-backed enum support: backing type is detected and cached; database values are cast to `int` before `BackedEnum::from()` when the backing type is `int`
- `AbstractPhpEnumType::$backingTypeCache` — caches backing type reflection results per enum class to avoid repeated `ReflectionEnum` calls
- `AbstractPhpEnumType::clearCache()` — now also clears `$backingTypeCache`
- `AbstractSetType::convertToDatabaseValue()` — validates that each element is an instance of the declared enum class before conversion; rejects mismatched enum cases with `InvalidTypeValueException`
- `AbstractSetType::convertToPHPValue()` — throws `InvalidTypeValueException` when the raw database value is not a string

### Changed

- `AbstractPhpEnumType::getEnumByName()` — uses `\constant()` with `Error` catch instead of `::cases()` iteration; return type narrowed to `UnitEnum`
- `AbstractPhpEnumType::getEnumByValue()` — return type narrowed to `BackedEnum`
- `AbstractSetType::convertToDatabaseValue()` — filters out empty strings in addition to `null` values
- `AbstractSetType::getSQLDeclaration()` — casts `convertValueToDatabase()` result to `string` before `quoteStringLiteral()`

## [v3.0.2] - 2026-04-06

### Fixed

- `AbstractPhpEnumType::getEnumByName()` — replaced `constant()` with `::cases()` iteration to avoid resolving arbitrary class constants from database values

### Changed

- Added `\` prefix to all built-in function calls across `AbstractPhpEnumType`, `AbstractSetType`, `AbstractEnumType`, and `TinyintType` for consistency
- Removed unused `use Error` import from `AbstractPhpEnumType`

## [v3.0.1] - 2026-04-04

### Changed

- Upgraded from PHPUnit 9 to PHPUnit 11.5 via `precision-soft/symfony-phpunit: ^3.0`
- Replaced `<coverage processUncoveredFiles="true">` with `<source>` element in `phpunit.xml.dist`
- Replaced `<listeners>` with `<extensions>` using `Symfony\Bridge\PhpUnit\SymfonyExtension`
- Added `failOnRisky` and `failOnWarning` attributes to `phpunit.xml.dist`
- Added `tests/` to PHPStan analysis paths in `phpstan.neon`
- Added PHPStan type annotations to anonymous test classes in `AbstractPhpEnumTypeTest`
- Uppercased `TINYINT` SQL keyword in `TinyintType::getSQLDeclaration()` and `validateRange()` error message
- Quoted `$COMPOSER_DEV_MODE` variable in `composer.json` hook script

## [v3.0.0] - 2026-04-03

### Breaking Changes

- `AbstractPhpEnumType::convertValueToDatabase()` visibility narrowed from `public` to `protected`
- `AbstractPhpEnumType::convertValueToPhp()` visibility narrowed from `public` to `protected`
- `TinyintType::convertToDatabaseValue()` return type narrowed from `int|string|null` to `?int` (numeric strings now return `int`)
- `AbstractSetType::convertToDatabaseValue()` now throws `InvalidTypeValueException` when passed a non-array value (previously silently cast to array)
- `DateTimeType` column option `update` now requires strict `true` (previously accepted any truthy value)
- Dropped Doctrine DBAL 3 support (requires DBAL 4)
- Removed `TinyintType::getName()` (DBAL 3 compatibility method)
- Removed `squizlabs/php_codesniffer` dev dependency and `phpcs.xml` configuration
- Renamed `phpunit.xml` to `phpunit.xml.dist`
- Renamed dev directory from `dev/` to `.dev/`

### Added

- `AbstractType::requiresSQLCommentHint()` returns `true` so Doctrine schema tools detect custom type changes
- `AbstractSetType::convertToDatabaseValue()` — deduplicates values with `array_unique()`
- `AbstractSetType::convertToPHPValue()` — `@return` PHPDoc with typed array
- Null guards in `AbstractPhpEnumType` for `::cases()` and `::tryFrom()` calls
- PHPStan level 8 with empty baseline (zero ignored errors)
- `AbstractTypeTest`, `EnumTypeTest`, `ExceptionTest` test classes
- `TestConcreteType`, `TestPrefixedType` test utilities
- Docker `entrypoint.sh` for dev container, replacing `dev/docker/setup.sh`
- Type hierarchy section in README
- Cache section in README
- Security and troubleshooting guidance in README

### Changed

- All test classes extend `AbstractTestCase` from `precision-soft/symfony-phpunit`
- Upgraded PHPStan from `^1.0` to `^2.0`
- Replaced `php_codesniffer` with PHPStan for static analysis
- Replaced `strpos()` with `str_contains()` in `AbstractSetType`
- Replaced `class_exists()` + `enum_exists()` with `enum_exists()` only in `AbstractPhpEnumType::getEnumType()`
- Removed `TinyintType::validateRange()` unused `$unsigned` parameter
- Improved exception messages with context
- Descriptive variable names across all source and test files (no generic `$value`, `$result`)
- Removed redundant PHPDoc comments

### Fixed

- `AbstractEnumType::getSQLDeclaration()` — non-MySQL fallback uses `getStringTypeDeclarationSQL()` instead of `getIntegerTypeDeclarationSQL()`
- `AbstractSetType::getSQLDeclaration()` — same fix as above
- `TinyintType::getSQLDeclaration()` — throws `InvalidTypeValueException` instead of base `Exception` on non-MySQL platforms
- `TinyintType::validateRange()` — uses Yoda conditions

## [v2.2.3] - 2026-03-20

### Fixed

- `AbstractSetType::convertToDatabaseValue()` — filtered null values
- Corrected clone URL in README

## [v2.2.2] - 2026-03-19

### Fixed

- `TinyintType::convertToDatabaseValue()` — added tinyint range validation
- `AbstractSetType::convertToDatabaseValue()` — added set value comma validation

## [v2.2.1] - 2026-03-19

### Fixed

- Corrected `getSQLDeclaration` method name
- Replaced `empty()` with explicit comparisons

### Changed

- `composer.json` updates

## [v2.2.0] - 2026-03-19

### Changed

- Improved enum type handling with `EnumType` enum for type resolution
- Modernized `TinyintType` with stricter validation

## [v2.1.0] - 2026-03-13

### Changed

- Normalized code style across all source files

## [v2.0.2] - 2024-11-24

### Fixed

- `AbstractPhpEnumType::convertValueToDatabase()` — handling for non-backed enums

## [v2.0.1] - 2024-10-17

### Fixed

- `TinyintType::getBindingType()` return type

## [v2.0.0] - 2024-10-17

### Added

- Doctrine DBAL 4 support

## [v1.1.0] - 2024-10-16

### Added

- PHP enum support for `AbstractEnumType` and `AbstractSetType`
- `AbstractType::getDefaultName()` and `getDefaultNamePrefix()`

### Changed

- Code reformatting

## [v1.0.1] - 2024-09-26

### Fixed

- `TinyintType::getBindingType()` return value
- php-cs-fixer configuration

## [v1.0.0] - 2024-09-17

### Added

- `AbstractEnumType` for MySQL `ENUM` columns
- `AbstractSetType` for MySQL `SET` columns
- `DateTimeType` with `ON UPDATE CURRENT_TIMESTAMP` support
- `TinyintType` for MySQL `TINYINT` columns
- Project-specific exception hierarchy

[Unreleased]: https://github.com/precision-soft/doctrine-type/compare/v3.4.1...HEAD

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
