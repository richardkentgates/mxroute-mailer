# Development

This guide covers contributing to MXRoute Mailer, setting up a development environment, and understanding the CI/CD pipeline.

## Getting Started

### Prerequisites

- PHP 7.3+ with required extensions
- [Composer](https://getcomposer.org/)
- [Git](https://git-scm.com/)
- [Node.js](https://nodejs.org/) (optional, for build tools)

### Clone and Setup

```bash
# Clone the repository
git clone https://github.com/richardkentgates/mxroute-mailer.git
cd mxroute-mailer

# Switch to the dev branch
git checkout dev

# Install dependencies
composer install
```

### Run Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run a specific test file
vendor/bin/phpunit tests/test-class-settings.php

# Run with coverage report
vendor/bin/phpunit --coverage-html coverage/
```

### Run Code Style Checks

```bash
# Check for violations
vendor/bin/phpcs --standard=phpcs.xml.dist --extensions=php --ignore=vendor/*,tests/*

# Auto-fix violations
vendor/bin/phpcbf --standard=phpcs.xml.dist --extensions=php --ignore=vendor/*,tests/*
```

## Project Structure

```
mxroute-mailer/
├── mxroute-mailer.php          # Main plugin file, constants, activation hook
├── includes/
│   ├── class-mxroute-api.php       # MXRoute HTTP API client
│   ├── class-mxroute-mailer.php    # Core mail interception and routing
│   ├── class-mxroute-settings.php  # Settings page, menus, sanitization
│   ├── class-mxroute-logger.php    # Email logging to database
│   ├── class-mxroute-dashboard.php # AJAX handlers for log management
│   └── class-mxroute-updater.php   # GitHub-based auto-updater
├── admin/
│   ├── views/
│   │   ├── settings.php        # Settings page template
│   │   ├── logs.php            # Logs list page template
│   │   └── log-view.php        # Single log detail template
│   └── css/
│       └── admin.css           # Admin styles
├── tests/
│   ├── bootstrap.php           # Test bootstrap with mocks
│   ├── test-mxroute-mailer.php # Core mailer tests
│   ├── test-class-settings.php # Settings tests
│   ├── test-class-logger.php   # Logger tests
│   ├── test-class-dashboard.php # Dashboard AJAX tests
│   ├── test-class-mxroute-api.php # API client tests
│   └── test-edge-cases.php     # Edge case and boundary tests
├── .github/
│   └── workflows/
│       ├── ci.yml              # CI pipeline (PHPCS + PHPUnit)
│       ├── release.yml         # Release build and publish
│       ├── promote-dev-to-test.yml  # Dev → Test promotion
│       └── promote-test-to-main.yml # Test → Main promotion
├── wiki/                       # GitHub wiki pages (local copies)
├── readme.txt                  # WordPress plugin readme
├── LICENSE                     # GPLv2 license
└── phpunit.xml                 # PHPUnit configuration
```

## Branch Strategy

### Branches

- **`dev`** - Active development. All new work targets this branch.
- **`test`** - Testing and CI validation. Merged from `dev`.
- **`main`** - Production-ready code. Only merged from `test` after all checks pass.

### Workflow

1. Create a feature branch from `dev`:
   ```bash
   git checkout dev
   git checkout -b feature/my-feature
   ```

2. Make changes and commit:
   ```bash
   git add -A
   git commit -m "Add my feature"
   ```

3. Push and create a pull request targeting `dev`

4. After merge, promote through the pipeline:
   - Dev → Test (CI runs automatically)
   - Test → Main (after CI passes)

### Promotion Workflows

The promotion workflows are triggered via GitHub Actions `workflow_dispatch`:

1. **Dev → Test**: Runs PHPCS and PHPUnit on all PHP versions (7.3, 7.4, 8.0, 8.1, 8.2, 8.3)
2. **Test → Main**: Same CI checks, then merges to main and builds a zip artifact

## CI/CD Pipeline

### GitHub Actions Workflows

#### CI Pipeline (`ci.yml`)
- **Trigger**: Push to dev, test, or main branches
- **Jobs**:
  - PHPCS: Code style checks
  - PHPUnit: Tests on PHP 7.3, 7.4, 8.0, 8.1, 8.2, 8.3

#### Release Pipeline (`release.yml`)
- **Trigger**: Push of a `v*` tag
- **Jobs**:
  - PHPCS + PHPUnit checks
  - Build zip archive
  - Create GitHub release with zip attached

#### Dev → Test Promotion (`promote-dev-to-test.yml`)
- **Trigger**: Manual dispatch
- **Jobs**:
  - CI checks (PHPCS + PHPUnit)
  - Merge dev into test
  - Build test artifact

#### Test → Main Promotion (`promote-test-to-main.yml`)
- **Trigger**: Manual dispatch
- **Jobs**:
  - CI checks (PHPCS + PHPUnit)
  - Merge test into main
  - Build main artifact

### Releasing a New Version

1. Update version in:
   - `mxroute-mailer.php` (Plugin header and `MXROUTE_MAILER_VERSION` constant)
   - `readme.txt` (Stable tag and changelog)

2. Merge through the pipeline:
   ```bash
   git checkout test && git merge dev && git push origin test
   # Wait for CI to pass
   git checkout main && git merge test && git push origin main
   ```

3. Create and push a release tag:
   ```bash
   git tag v1.2.5
   git push origin v1.2.5
   ```

4. The release workflow automatically:
   - Runs final CI checks
   - Builds the zip file
   - Creates a GitHub release with the zip attached

## Coding Standards

### WordPress Coding Standards

All code follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/):

- Tabs for indentation (not spaces)
- Yoda conditions (`if ( true === $var )`)
- Snake_case for functions and variables
- PascalCase for class names
- Full docblocks on all public methods

### Security Practices

- Always sanitize input: `sanitize_email()`, `sanitize_text_field()`, `sanitize_textarea_field()`
- Always escape output: `esc_html()`, `esc_attr()`, `esc_url()`
- Use `$wpdb->prepare()` for all database queries
- Use nonces for form submissions
- Never log or expose credentials

### Docblocks

All classes, methods, and properties must have docblocks:

```php
/**
 * Class description.
 *
 * @package MXRoute_Mailer
 */
class MXRoute_Example {

    /**
     * Property description.
     *
     * @var string
     */
    private $property;

    /**
     * Method description.
     *
     * @param string $param Parameter description.
     * @return string Return value description.
     */
    public function method( $param ) {
        return $param;
    }
}
```

## Testing

### Test Structure

- Unit tests in `tests/test-*.php`
- Mock WordPress functions in `tests/bootstrap.php`
- Edge case tests in `tests/test-edge-cases.php`

### Writing Tests

```php
/**
 * Tests for Example functionality.
 */
class MXRoute_Example_Test extends WP_UnitTestCase {

    /**
     * Test that something works correctly.
     */
    public function test_example_works() {
        $result = mxroute_mailer_example();
        $this->assertEquals( 'expected', $result );
    }
}
```

### Mock System

The test bootstrap mocks WordPress functions to allow testing without a full WordPress installation. Key mocks include:

- `WP_UnitTestCase` base class
- `$wpdb` database abstraction
- All `wp_mail`, `get_option`, `update_option` functions
- Nonce verification
- Sanitization and escaping functions

## Database Migrations

When adding new database columns:

1. Add the column to the `create_table()` method in `class-mxroute-logger.php`
2. Add a migration in `mxroute_mailer_db_upgrade()` that:
   - Checks if the column exists
   - Adds it if missing
   - Updates the `mxroute_mailer_db_version` option
3. The migration runs automatically on `admin_init`

## Contributing

See [CONTRIBUTING.md](.github/CONTRIBUTING.md) for detailed contribution guidelines.
