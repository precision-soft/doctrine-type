# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v3.2.1] - 2026-04-13

### Fixed

- `TinyintType::convertToPHPValue()` — now calls `validateRange()` for both `int` and integer-formatted `string` inputs, rejecting out-of-range values with `InvalidTypeValueException` (previously only `convertToDatabaseValue()` enforced the range)

### Changed

- `TinyintType` — extracted out-of-range error into protected `throwOutOfRangeException(int $value): never` helper for reuse and subclass overrides

## [v3.2.0] - 2026-04-12

### Changed

- `AbstractPhpEnumType` — `$enumTypeCache` and `$backingTypeCache` visibility widened from `private static` to `protected static` to allow subclass access
- `AbstractPhpEnumType` — `getEnumType()`, `getEnumByName()`, `getEnumByValue()` visibility widened from `private` to `protected`
- `AbstractPhpEnumType::getEnumByName()` — replace `try/catch Error` with `\defined()` check before `\constant()` call; removed unused `use Error` import
- `AbstractPhpEnumType::getValues()` — PHPDoc return type narrowed from `array<int, mixed>` to `array<int, UnitEnum>`
- `TinyintType` — `validateRange()` visibility widened from `private` to `protected`

### Tests

- `AbstractSetTypeTest` — add `convertToPHPValue` tests for non-string types (int, array) and whitespace-padded set values
- `TinyintTypeTest` — add `convertToPHPValue` tests for invalid types (float, bool, array, object, non-numeric string)

## [v3.1.2] - 2026-04-10

### Fixed

- `TinyintType::convertToPHPValue()` — validates that the input is an `int` or an integer-formatted string; throws `InvalidTypeValueException` for any other type

### Changed

- `AbstractSetType::convertToDatabaseValue()` — refactored null guard to early return for clarity

## [v3.1.1] - 2026-04-07

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

- `AbstractPhpEnumType::getEnumByName()` — replace `constant()` with `::cases()` iteration to avoid resolving arbitrary class constants from database values

### Changed

- Add `\` prefix to all built-in function calls across `AbstractPhpEnumType`, `AbstractSetType`, `AbstractEnumType`, and `TinyintType` for consistency
- Remove unused `use Error` import from `AbstractPhpEnumType`

## [v3.0.1] - 2026-04-04

### Changed

- Upgrade from PHPUnit 9 to PHPUnit 11.5 via `precision-soft/symfony-phpunit: ^3.0`
- Replace `<coverage processUncoveredFiles="true">` with `<source>` element in `phpunit.xml.dist`
- Replace `<listeners>` with `<extensions>` using `Symfony\Bridge\PhpUnit\SymfonyExtension`
- Add `failOnRisky` and `failOnWarning` attributes to `phpunit.xml.dist`
- Add `tests/` to PHPStan analysis paths in `phpstan.neon`
- Add PHPStan type annotations to anonymous test classes in `AbstractPhpEnumTypeTest`
- Uppercase `TINYINT` SQL keyword in `TinyintType::getSQLDeclaration()` and `validateRange()` error message
- Quote `$COMPOSER_DEV_MODE` variable in `composer.json` hook script

## [v3.0.0] - 2026-04-03

### Breaking Changes

- `AbstractPhpEnumType::convertValueToDatabase()` visibility narrowed from `public` to `protected`
- `AbstractPhpEnumType::convertValueToPhp()` visibility narrowed from `public` to `protected`
- `TinyintType::convertToDatabaseValue()` return type narrowed from `int|string|null` to `?int` (numeric strings now return `int`)
- `AbstractSetType::convertToDatabaseValue()` now throws `InvalidTypeValueException` when passed a non-array value (previously silently cast to array)
- `DateTimeType` column option `update` now requires strict `true` (previously accepted any truthy value)
- Removed `squizlabs/php_codesniffer` from dev dependencies
- Renamed `phpunit.xml` to `phpunit.xml.dist`
- Renamed dev directory from `dev/` to `.dev/`
- Dropped Doctrine DBAL 3 support (requires DBAL 4)
- Removed `TinyintType::getName()` (DBAL 3 compatibility method)

### Fixed

- `AbstractEnumType::getSQLDeclaration()` non-MySQL fallback uses `getStringTypeDeclarationSQL()` instead of incorrect `getIntegerTypeDeclarationSQL()`
- `AbstractSetType::getSQLDeclaration()` same fix as above
- `TinyintType::getSQLDeclaration()` throws `InvalidTypeValueException` instead of base `Exception` on non-MySQL platforms
- `TinyintType::validateRange()` uses Yoda conditions

### Added

- `AbstractType::requiresSQLCommentHint()` returns `true` so Doctrine schema tools detect custom type changes
- `AbstractSetType::convertToDatabaseValue()` deduplicates values with `array_unique()`
- `AbstractSetType::convertToPHPValue()` `@return` PHPDoc with typed array
- Null guards in `AbstractPhpEnumType` for `::cases()` and `::tryFrom()` calls
- PHPStan level 8 with empty baseline (zero ignored errors)
- `AbstractTypeTest`, `EnumTypeTest`, `ExceptionTest` test classes
- `TestConcreteType`, `TestPrefixedType` test utilities
- Type hierarchy section in README
- Cache section in README
- Security and troubleshooting guidance in README
- Docker `entrypoint.sh` for dev container

### Changed

- All test classes extend `AbstractTestCase` from `precision-soft/symfony-phpunit`
- Upgraded PHPStan from `^1.0` to `^2.0`
- Replaced `php_codesniffer` with PHPStan for static analysis
- Replaced `strpos()` with `str_contains()` in `AbstractSetType`
- Replaced `class_exists()` + `enum_exists()` with `enum_exists()` only in `AbstractPhpEnumType::getEnumType()`
- Removed `TinyintType::validateRange()` unused `$unsigned` parameter
- Improved exception messages with context
- Descriptive variable names across all source and test files (no generic `$value`, `$result`)

### Removed

- `squizlabs/php_codesniffer` dev dependency
- `phpcs.xml` configuration file
- `dev/docker/setup.sh` (replaced by `entrypoint.sh`)
- All PHPStan baseline ignores (baseline is now empty)
- Redundant PHPDoc comments

## [v2.2.3] - 2026-03-20

### Fixed

- Filter null values in `AbstractSetType::convertToDatabaseValue()`
- Correct clone URL in README

## [v2.2.2] - 2026-03-19

### Fixed

- Add tinyint range validation in `TinyintType::convertToDatabaseValue()`
- Add set value comma validation in `AbstractSetType::convertToDatabaseValue()`

## [v2.2.1] - 2026-03-19

### Fixed

- Correct `getSQLDeclaration` method name
- Replace `empty()` with explicit comparisons

### Changed

- Update composer.json

## [v2.2.0] - 2026-03-19

### Changed

- Improve enum type handling with `EnumType` enum for type resolution
- Modernize `TinyintType` with stricter validation

## [v2.1.0] - 2026-03-13

### Changed

- Normalize code style across all source files

## [v2.0.2] - 2024-11-24

### Fixed

- `AbstractPhpEnumType::convertValueToDatabase()` handling for non-backed enums

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
