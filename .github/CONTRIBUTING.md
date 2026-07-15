# Contributing to MXRoute Mailer

Thank you for your interest in contributing to MXRoute Mailer! This document provides guidelines and information for contributors.

## Getting Started

1. Fork the repository on GitHub
2. Clone your fork locally
3. Create a feature branch from `dev`
4. Make your changes
5. Run tests and linting
6. Submit a pull request

## Development Setup

### Prerequisites

- PHP 7.3 or higher
- [Composer](https://getcomposer.org/)
- [WP-CLI](https://wp-cli.org/) (optional, for local WordPress testing)

### Installation

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/mxroute-mailer.git
cd mxroute-mailer

# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run code style checks
./vendor/bin/phpcs --standard=phpcs.xml.dist
```

## Branch Strategy

We use a three-branch workflow:

- **`dev`** - Development branch. All new work goes here first.
- **`test`** - Testing branch. Merged from `dev` for CI validation.
- **`main`** - Production branch. Only merged from `test` after all checks pass.

### Workflow

1. Create a feature branch from `dev`
2. Make your changes and commit
3. Push to your fork
4. Open a pull request targeting `dev`
5. After review and CI passes, `dev` is promoted to `test`
6. After `test` CI passes, `test` is promoted to `main`
7. A release tag triggers the build and deploy workflow

## Code Standards

### PHP

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use the provided `phpcs.xml.dist` configuration
- All code must pass PHPCS checks
- All code must pass PHPUnit tests

### Running Checks

```bash
# Run PHPCS
vendor/bin/phpcs --standard=phpcs.xml.dist --extensions=php --ignore=vendor/*,tests/*

# Run PHPUnit
vendor/bin/phpunit

# Auto-fix PHPCS issues
vendor/bin/phpcbf --standard=phpcs.xml.dist --extensions=php --ignore=vendor/*,tests/*
```

### Docblocks

- All classes must have a class-level docblock
- All public methods must have docblocks with `@param` and `@return` tags
- All properties must have `@var` annotations
- Use the `@package MXRoute_Mailer` tag

### Security

- Always use `sanitize_*` functions for user input
- Always use `esc_*` functions for output
- Use nonces for form submissions
- Use `$wpdb->prepare()` for database queries
- Never log or expose sensitive data (passwords, API keys)

## Testing

### Writing Tests

- Place tests in the `tests/` directory
- Name test files `test-*.php`
- Extend `WP_UnitTestCase` for unit tests
- Use descriptive method names: `test_sanitize_username_strips_domain()`

### Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run a specific test file
vendor/bin/phpunit tests/test-class-settings.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

## Pull Request Guidelines

### Before Submitting

- [ ] Code follows WordPress Coding Standards
- [ ] All PHPCS checks pass
- [ ] All PHPUnit tests pass
- [ ] New tests added for new functionality
- [ ] Documentation updated (readme.txt, wiki if applicable)
- [ ] Version number bumped in `mxroute-mailer.php` and `readme.txt`

### PR Description

Include:
- What the change does
- Why the change is needed
- How to test the change
- Any breaking changes

### Commit Messages

- Use present tense ("Add feature" not "Added feature")
- Use imperative mood ("Fix bug" not "Fixes bug")
- Keep the first line under 72 characters
- Reference issue numbers when applicable

## Reporting Issues

### Bug Reports

Include:
- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Error messages or logs

### Feature Requests

- Describe the use case
- Explain why the feature would be useful
- Suggest how it could be implemented

## Release Process

1. Update version in `mxroute-mailer.php` and `readme.txt`
2. Update changelog in `readme.txt`
3. Merge `dev` -> `test` -> `main`
4. Create a GitHub release with a `v*` tag (e.g., `v1.2.5`)
5. The CI/CD pipeline automatically builds and attaches the zip

## Questions?

Open an issue on GitHub or check the [wiki](https://github.com/richardkentgates/mxroute-mailer/wiki) for documentation.
