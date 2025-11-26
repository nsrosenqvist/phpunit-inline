# Agent Instructions

This document provides instructions for AI coding agents working on this project.

## Before Considering Any Task Complete

Always run the full check suite before marking a task as done:

```bash
composer check
```

This command runs all quality checks in sequence:
1. **Code formatting check** (`composer format:check`) - Verifies PSR-12 compliance
2. **Static analysis** (`composer analyze`) - Runs PHPStan analysis
3. **Tests** (`composer test`) - Runs the PHPUnit test suite

All three checks must pass for the code to be considered complete.

## Code Quality Requirements

### Coding Standards

- All code must follow **PSR-12** coding standard
- All PHP files must include `declare(strict_types=1);` at the top

### Available Commands

| Command | Description |
|---------|-------------|
| `composer check` | Run all checks (formatting, static analysis, tests) |
| `composer format` | Auto-fix code formatting issues |
| `composer format:check` | Check formatting without making changes |
| `composer analyze` | Run PHPStan static analysis |
| `composer test` | Run PHPUnit tests |

### Fixing Issues

If `composer check` fails:

1. **Formatting errors**: Run `composer format` to auto-fix, then verify with `composer format:check`
2. **Static analysis errors**: Review PHPStan output and fix type issues manually
3. **Test failures**: Debug and fix the failing tests

## Workflow Summary

1. Make your code changes
2. Run `composer format` to fix any formatting issues
3. Run `composer check` to verify all checks pass
4. Only consider the task complete when all checks pass
