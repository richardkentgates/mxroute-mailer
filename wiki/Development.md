# Development

This guide covers contributing to MXRoute Mailer, setting up a development environment, and understanding the CI/CD pipeline.

## Getting Started

### Prerequisites

- PHP 7.3+ with the `curl`, `json`, and `mbstring` extensions
- [Git](https://git-scm.com/)
- [WP-CLI](https://wp-cli.org/) (optional, for local WordPress testing)

No Composer or Node.js is required. The project uses the official PHPUnit PHAR and PHP's built-in lint command.

### Clone and Setup

```bash
# Clone the repository
git clone https://github.com/richardkentgates/mxroute-mailer.git
cd mxroute-mailer

# Switch to the dev branch
git checkout dev
```

## Running Tests

Tests are run with the official PHPUnit PHAR. The CI workflow downloads the PHAR automatically, but locally you can do the same:

```bash
# Download a PHPUnit PHAR compatible with PHP 7.3+
curl -Lo phpunit.phar https://phar.phpunit.de/phpunit-9.phar
chmod +x phpunit.phar

# Run all tests
./phpunit.phar --configuration phpunit.xml

# Run a specific test file
./phpunit.phar --configuration phpunit.xml tests/test-class-settings.php

# Run PHP syntax lint on all plugin files
find . -type f -name '*.php' ! -path './vendor/*' ! -path './tests/*' -print0 | xargs -0 -n1 php -l
```

## Project Structure

```
mxroute-mailer/
├── mxroute-mailer.php              # Main plugin file, constants, activation hook
├── includes/
│   ├── class-mxroute-api.php       # MXRoute HTTP API client
│   ├── class-mxroute-crypto.php    # Reversible encryption for sensitive options
│   ├── class-mxroute-mailer.php    # Core mail interception and routing
│   ├── class-mxroute-settings.php  # Settings page, menus, help tabs
│   ├── class-mxroute-logger.php    # Email logging to database
│   ├── class-mxroute-queue.php     # Queue CRUD operations
│   ├── class-mxroute-dashboard.php # AJAX handlers for log management
│   └── class-mxroute-updater.php   # GitHub-based auto-updater
├── admin/
│   ├── views/
│   │   ├── settings.php            # Settings page template
│   │   ├── logs.php                # Logs list page template
│   │   ├── log-view.php            # Single log detail template
│   │   └── queue.php               # Queue status page template
│   ├── css/admin.css               # Admin styles
│   └── js/admin.js                 # Admin scripts
├── tests/
│   ├── bootstrap.php               # Test bootstrap with mocks
│   ├── test-mxroute-mailer.php     # Core mailer tests
│   ├── test-class-settings.php     # Settings tests
│   ├── test-class-logger.php       # Logger tests
│   ├── test-class-dashboard.php    # Dashboard AJAX tests
│   ├── test-class-queue.php        # Queue and API tests
│   ├── test-class-crypto.php       # Encryption tests
│   ├── test-class-updater.php      # Updater tests
│   ├── test-class-mxroute-api.php  # API client tests
│   ├── test-edge-cases.php         # Edge case and boundary tests
│   └── test-coverage-gaps.php      # Coverage gap tests
├── .github/
│   ├── workflows/
│   │   ├── ci.yml                  # Quality and security checks
│   │   ├── version-bump.yml        # Auto patch-version bump on dev push
│   │   ├── promote-to-test.yml     # Dev → Test promotion
│   │   ├── promote-to-main.yml     # Test → Main promotion
│   │   └── release.yml             # Release build and publish
│   └── CONTRIBUTING.md             # Contribution guidelines
├── wiki/                           # GitHub wiki pages (local copies)
├── readme.txt                      # WordPress plugin readme
├── LICENSE                         # GPLv2 license
├── phpunit.xml                     # PHPUnit configuration
└── PROMOTION.md                    # Exact promotion workflow directive
```

## Branch Strategy

### Branches

- **`dev`** - Active development. All new work targets this branch.
- **`test`** - Testing branch. Merged from `dev` through the Promote to Test workflow.
- **`main`** - Production branch. Merged from `test` through the Promote to Main workflow.

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

3. Push and create a pull request targeting `dev`.

4. After the PR is merged, the code is promoted through the pipeline manually:
   - Dev → Test
   - Test → Main

## Promotion Workflows

The promotion workflows are triggered manually via `workflow_dispatch`. Always run them from the correct source branch.

### Dev → Test

```bash
gh workflow run "Promote to Test" --repo richardkentgates/mxroute-mailer --ref dev
```

This merges `dev` into `test` and uploads a test artifact.

### Test → Main

```bash
gh workflow run "Promote to Main" --repo richardkentgates/mxroute-mailer --ref test
```

This merges `test` into `main`, creates the release tag, and triggers the Release workflow.

### Version Bump

Every human push to `dev` automatically increments the patch version in `mxroute-mailer.php`. The bump commit is made by `github-actions[bot]` with `[version] [skip ci]` in the message so it does not re-trigger CI.

## CI/CD Pipeline

### Quality and Security Checks (`ci.yml`)

**Trigger:** Push to `dev`.

**Jobs:**

- **PHP Syntax Lint** - `php -l` on all PHP files
- **PHPUnit** - Unit tests on PHP 7.3, 7.4, 8.0, 8.1, 8.2, and 8.3 using the official PHPUnit PHAR
- **Security - OSSF Scorecard Replacement** - Runs `zizmor` on workflow files and verifies all Actions references are pinned to a SHA
- **Security - CodeQL & PHP Vulnerability Scan** - Runs CodeQL analysis and a Semgrep PHP security scan
- **Build Artifact** - Builds a test zip with a top-level `mxroute-mailer/` folder

### Auto Bump Version (`version-bump.yml`)

**Trigger:** Push to `dev`.

Bumps the patch version in `mxroute-mailer.php` and pushes the change back to `dev`.

### Promote to Test (`promote-to-test.yml`)

**Trigger:** Manual dispatch from the `dev` branch.

- Validates the source branch is `dev`
- Creates or finds an open PR from `dev` to `test`
- Merges the PR
- Builds a test zip artifact

### Promote to Main (`promote-to-main.yml`)

**Trigger:** Manual dispatch from the `test` branch.

- Validates the source branch is `test`
- Creates or finds an open PR from `test` to `main`
- Merges the PR
- Checks out `origin/main` and creates the release tag
- Triggers the Release workflow for the new tag

### Release (`release.yml`)

**Trigger:** Push of a `v*` tag, or manual dispatch.

- Builds the release zip in `/tmp` with a top-level `mxroute-mailer/` folder
- Creates a GitHub release and attaches the zip

## Releasing a New Version

There is no manual version-editing step for patch releases. The pipeline handles it:

1. Merge your feature PR into `dev`.
2. The Auto Bump Version workflow increments the patch version (e.g., `1.2.14` → `1.2.15`).
3. Run Promote to Test from `dev`.
4. Run Promote to Main from `test`.
5. The Release workflow creates the GitHub release with the zip attached.

If you need a minor or major version bump, update the version manually in `mxroute-mailer.php` before pushing to `dev`.

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
- Gate debug logging behind `MXROUTE_MAILER_DEBUG` constant — never log passwords or sensitive data

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
class MXRoute_Example_Test extends \PHPUnit\Framework\TestCase {

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

- `\PHPUnit\Framework\TestCase` base class (not `WP_UnitTestCase`)
- `$wpdb` database abstraction with configurable query results (`get_results`, `get_var`, `get_row`, `get_col`, `insert`, `update`, `delete`, `prepare`)
- WordPress functions: `get_option`, `update_option`, `delete_option`, `add_action`, `add_filter`, `do_action`, `wp_upload_dir`, `wp_basename`
- Nonce functions: `wp_create_nonce`, `wp_verify_nonce`, `check_ajax_referer`
- AJAX response functions: `wp_send_json_success`, `wp_send_json_error` (throw `MXRouteJSONException`)
- Sanitization and escaping functions: `sanitize_email`, `sanitize_text_field`, `esc_html`, `esc_attr`
- PHPMailer mock with configurable success port for SMTP smart switch testing
- `wp_remote_post`, `wp_remote_get` mocks with configurable responses
- `current_user_can` mock with configurable return value via `$GLOBALS['wp_mock_current_user_can']`
- Constants: `MB_IN_BYTES`, `DAY_IN_SECONDS`, `ABSPATH`, `OBJECT`, `ARRAY_A`, `ARRAY_N`

## Database Migrations

When adding new database columns:

1. Add the column to the `create_table()` method in `class-mxroute-logger.php`
2. Add a migration in `mxroute_mailer_db_upgrade()` that:
   - Checks if the column exists
   - Adds it if missing
   - Updates the `mxroute_mailer_db_version` option
3. The migration runs automatically on `admin_init`

## Contributing

See [CONTRIBUTING.md](../.github/CONTRIBUTING.md) for detailed contribution guidelines.
