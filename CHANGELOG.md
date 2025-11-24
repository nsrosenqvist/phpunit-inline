# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial implementation of inline tests extension
- Support for PHPUnit's native `#[Test]` attribute
- `InlineTestScanner` for discovering tests in application code
- `TestProxy` for providing access to both private methods and PHPUnit assertions
- `InlineTestExtension` for PHPUnit integration
- PHPStan plugin for better static analysis support
- Comprehensive documentation and examples
- Full test coverage for extension functionality

### Features
- Write tests directly in application classes using `#[Test]` attribute
- Access private and protected methods without reflection hacks
- Full PHPUnit feature support (assertions, mocking, etc.)
- Configurable directory scanning
- PSR-12 compliant code with strict types
- Compatible with PHPUnit 11 and 12

## [1.0.0] - TBD

### Added
- First stable release
