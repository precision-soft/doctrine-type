# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

## [v2.2.1] - 2026-03-19 - Correct getSQLDeclaration casing and replace empty() with explicit checks

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

[Unreleased]: https://github.com/precision-soft/doctrine-type/compare/v3.4.2...HEAD

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
