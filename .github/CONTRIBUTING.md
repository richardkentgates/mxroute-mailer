# Contributing to MXRoute Mailer

Thank you for your interest in contributing to MXRoute Mailer! This document provides guidelines and information for contributors.

## Getting Started

1. Fork the repository on GitHub
2. Clone your fork locally
3. Create a feature branch from `dev`
4. Make your changes
5. Run tests and linting
6. Submit a pull request targeting `dev`

## Development Setup

### Prerequisites

- PHP 7.3 or higher
- [Git](https://git-scm.com/)
- [WP-CLI](https://wp-cli.org/) (optional, for local WordPress testing)

No Composer or Node.js is required. Tests run with the official PHPUnit PHAR, and code style is enforced through PHP syntax lint plus manual WordPress Coding Standards review.

### Installation

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/mxroute-mailer.git
cd mxroute-mailer

# Switch to dev
git checkout dev
```

## Running Tests

```bash
# Download PHPUnit PHAR
curl -Lo phpunit.phar https://phar.phpunit.de/phpunit-9.phar
chmod +x phpunit.phar

# Run all tests
./phpunit.phar --configuration phpunit.xml

# Run a specific test file
./phpunit.phar --configuration phpunit.xml tests/test-class-settings.php

# Run PHP syntax lint
find . -type f -name '*.php' ! -path './vendor/*' ! -path './tests/*' -print0 | xargs -0 -n1 php -l
```

## Branch Strategy

We use a three-branch workflow:

- **`dev`** - Development branch. All new work goes here first.
- **`test`** - Testing branch. Merged from `dev` through the Promote to Test workflow.
- **`main`** - Production branch. Only merged from `test` through the Promote to Main workflow.

### Workflow

1. Create a feature branch from `dev`
2. Make your changes and commit
3. Push to your fork
4. Open a pull request targeting `dev`
5. After merge, the repository owner promotes `dev` to `test`, then `test` to `main`
6. The Release workflow automatically builds the zip and creates a GitHub release

## Code Standards

### PHP

- Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use tabs for indentation
- Use Yoda conditions (`if ( true === $var )`)
- Use snake_case for functions and variables
- Use PascalCase for class names
- All code must pass PHP syntax lint
- All PHPUnit tests must pass

### Running Checks

```bash
# PHP syntax lint
find . -type f -name '*.php' ! -path './vendor/*' ! -path './tests/*' -print0 | xargs -0 -n1 php -l

# PHPUnit
./phpunit.phar --configuration phpunit.xml
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
- Extend `\PHPUnit\Framework\TestCase` (not `WP_UnitTestCase` — the bootstrap provides its own mocks)
- Use descriptive method names: `test_sanitize_username_strips_domain()`

### Mock System

The test bootstrap mocks WordPress functions to allow testing without a full WordPress installation. See `tests/bootstrap.php` for the full list. Key patterns:

- AJAX handlers throw `MXRouteJSONException` instead of calling `wp_die()`
- `$wpdb` uses `MockWPDB` with configurable responses via globals
- `MXRoute_Mailer::reset()` resets the singleton between tests

## Pull Request Guidelines

### Before Submitting

- [ ] Code follows WordPress Coding Standards
- [ ] PHP syntax lint passes
- [ ] All PHPUnit tests pass
- [ ] New tests added for new functionality
- [ ] Documentation updated (`readme.txt`, wiki if applicable)
- [ ] Do not bump the version number manually; the Auto Bump Version workflow handles patch bumps on `dev`

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

Patch releases are automated:

1. Merge changes into `dev`
2. The Auto Bump Version workflow increments the patch version in `mxroute-mailer.php`
3. Run the Promote to Test workflow from `dev`
4. Run the Promote to Main workflow from `test`
5. The Release workflow automatically builds the zip and creates the GitHub release

For minor or major releases, update the version manually in `mxroute-mailer.php` before pushing to `dev`.

## Questions?

Open an issue on GitHub or check the [wiki](https://github.com/richardkentgates/mxroute-mailer/wiki) for documentation.
