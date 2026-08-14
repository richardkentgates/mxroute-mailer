# Agent Guidance: MXRoute Mailer

This file is for AI agents and contributors who need to work on the MXRoute Mailer WordPress plugin.

## Project Purpose

MXRoute Mailer is a WordPress plugin that intercepts `wp_mail()` and sends email through MXRoute's HTTPS API (port 443) instead of SMTP. It is designed for hosting environments where outbound SMTP ports are blocked. It supports WordPress Multisite and includes WP-CLI commands for command-line management.

## Repository Layout

```
mxroute-mailer/
├── mxroute-mailer.php              # Plugin header, constants, activation hook, multisite support
├── includes/
│   ├── class-mxroute-api.php       # MXRoute HTTP API client (smart switch: API for no attachments, SMTP for attachments)
│   ├── class-mxroute-crypto.php    # Reversible AES-256-GCM encryption for sensitive options
│   ├── class-mxroute-mailer.php    # Core mail interception and queue routing
│   ├── class-mxroute-settings.php  # Admin settings pages, menus, and help tabs
│   ├── class-mxroute-logger.php    # Database email logging with filtering and pagination
│   ├── class-mxroute-queue.php     # Queue CRUD, attachment storage, and cleanup
│   ├── class-mxroute-dashboard.php # AJAX log management handlers
│   ├── class-mxroute-updater.php   # Apt server auto-updater with metadata.json
│   └── class-mxroute-cli.php       # WP-CLI commands: option, logs, queue, send, test
├── admin/
│   ├── views/                      # PHP templates for settings/logs/queue/log detail
│   ├── css/admin.css
│   └── js/admin.js
├── languages/
│   └── index.php                   # i18n directory (translations go here)
├── assets/
│   └── test-attachment.txt         # Persistent test attachment file
├── tests/                          # PHPUnit tests (237 tests, 476 assertions)
├── .github/workflows/              # CI/CD and promotion workflows
├── wiki/                           # Local copies of GitHub wiki pages
├── phpunit.xml                     # PHPUnit configuration
├── phpcs.xml.dist                  # PHP CodeSniffer configuration
├── .gitignore                      # Git ignore rules
├── readme.txt                      # WordPress.org-style readme
├── PROMOTION.md                    # Exact promotion workflow directive
└── AGENTS.md                       # This file
```

## Hard Constraints

- **No Composer.** Do not add `composer.json`, `vendor/`, or composer-based tools.
- **No Node.js build tools.** CSS/JS are plain files.
- **All code changes happen on `dev`.** Never commit directly to `test` or `main`.
- **`test` and `main` are promotion-only branches.** Promotions use the GitHub Actions workflows.
- **Do not re-tag releases.** If a release is broken, bump the version and run the pipeline again.
- **Release zips must contain a single top-level `mxroute-mailer/` folder.** A flat zip causes WordPress to install into a versioned folder and deactivate the plugin.
- **WordPress Coding Standards.** Tabs, Yoda conditions, snake_case functions/variables, PascalCase classes, full docblocks.
- **Security first.** Sanitize input, escape output, use nonces, use `$wpdb->prepare()`, never log credentials.
- **Debug logging uses `MXROUTE_MAILER_DEBUG` constant.** Never log passwords or sensitive data. Gate all debug output behind `defined( 'MXROUTE_MAILER_DEBUG' ) && MXROUTE_MAILER_DEBUG`.

## Testing Site

The project has a live testing environment for integration testing. It has:

- **WP_DEBUG_LOG** enabled (logs to `/wp-content/debug.log`)
- **Query Monitor** plugin installed for live debugging
- **WP-CLI** available via `php8.2 /usr/local/bin/wp`

To enable API debug logging on the test site, add to `wp-config.php`:
```php
define( 'MXROUTE_MAILER_DEBUG', true );
```

## Branch and Release Workflow

1. Work on `dev`.
2. Every human push to `dev` triggers **Auto Bump Version**, which increments the patch version in `mxroute-mailer.php`.
3. When ready, promote:
   - `gh workflow run "Promote to Test" --repo richardkentgates/mxroute-mailer --ref dev`
   - `gh workflow run "Promote to Main" --repo richardkentgates/mxroute-mailer --ref test`
4. Promote to Test merges dev→test, builds zip, deploys to apt server test channel.
5. Promote to Main merges test→main, creates tag, builds zip, creates GitHub release, deploys to apt server production channel.

See `PROMOTION.md` for the authoritative steps.

## CI/CD Workflows

| Workflow | File | Purpose |
|----------|------|---------|
| Quality and Security Checks | `.github/workflows/ci.yml` | Runs on push to `dev`. PHP lint, PHPUnit on PHP 7.3-8.3, zizmor, Semgrep, CodeQL, pinned-action check, artifact build. |
| Auto Bump Version | `.github/workflows/version-bump.yml` | Runs on push to `dev`. Bumps patch version in `mxroute-mailer.php`. |
| Promote to Test | `.github/workflows/promote-to-test.yml` | Manual. Merges `dev` into `test`, builds zip, deploys to apt server (test channel). Must be run with `--ref dev`. |
| Promote to Main | `.github/workflows/promote-to-main.yml` | Manual. Merges `test` into `main`, creates tag, builds zip, creates GitHub release, deploys to apt server (production channel). Must be run with `--ref test`. |

## GitHub Actions — Critical Rules

**READ THIS BEFORE DOING ANYTHING WITH GITHUB ACTIONS.**

1. **Workflow files MUST exist on the branch specified by `--ref`.** When you run `gh workflow run "X" --ref dev`, GitHub reads the workflow definition from the `dev` branch. If the file doesn't exist there, you get "Workflow does not have workflow_dispatch trigger." This was the root cause of the MXRoute Mailer workflow_dispatch failures. Deploy workflow files must be committed to BOTH `dev` and `main`.

2. **YAML must parse correctly.** A heredoc with JSON content (`{...}`) inside a `run:` block can break YAML parsing. GitHub falls back to using the filename as the workflow name instead of the YAML `name:` field. Always validate YAML with `python3 -c "import yaml; yaml.safe_load(open('file.yml'))"` before pushing.

3. **GitHub takes 2-5 minutes to process workflow file changes.** Do NOT check `gh workflow list` or `gh workflow run` immediately after pushing. Wait at least 2 minutes. Checking sooner gives false negatives.

4. **NEVER manually merge branches for promotion.** Always use `gh workflow run`. Manual merges bypass the build, deploy, and tagging steps. The workflow IS the process.

5. **NEVER bypass the promotion workflow because it "doesn't work."** Fix the workflow. Every bypass means the build, deploy, and release steps don't happen consistently.

6. **The three-step promotion flow is the same for every repo:**
   - Push to dev → CI + version bump (automated)
   - `gh workflow run "Promote X to Test" --ref dev` → merge, build, deploy to test channel
   - `gh workflow run "Promote X to Main" --ref test` → merge, tag, release, deploy to production channel

## Running Tests Locally

```bash
# Download PHPUnit PHAR
curl -Lo phpunit.phar https://phar.phpunit.de/phpunit-9.phar
chmod +x phpunit.phar

# Run tests
./phpunit.phar --configuration phpunit.xml

# Run PHP lint
find . -type f -name '*.php' ! -path './vendor/*' ! -path './tests/*' -print0 | xargs -0 -n1 php -l
```

## WP-CLI Commands

The plugin registers the `wp mxroute` command with these subcommands:

| Command | Description |
|---------|-------------|
| `wp mxroute option get [key]` | Get all settings or a specific setting |
| `wp mxroute option set <key> <value>` | Update a setting |
| `wp mxroute logs list` | List email logs with pagination |
| `wp mxroute logs view <id>` | View a specific log entry |
| `wp mxroute logs delete <id>` | Delete a log entry |
| `wp mxroute logs clear` | Clear all processed logs |
| `wp mxroute queue list` | List pending queue items |
| `wp mxroute queue count` | Count pending items |
| `wp mxroute queue clear` | Clear all pending items |
| `wp mxroute send <to> [subject] [message]` | Send email directly via MXRoute API |
| `wp mxroute test <to>` | Send a test email through the queue |

Commands are loaded conditionally via `WP_CLI` constant check. The CLI class is in `includes/class-mxroute-cli.php`.

## Multisite Support

- Per-site settings, logs, and cron
- Network activate/deactivate via `wpmu_activate_site` and `wpmu_deactivate_plugins`
- Automatic table creation on new sites via `wp_initialize_site` hook
- Per-site `keep_data` on uninstall
- Capability check helper: `mxroute_mailer_can_manage()` checks `manage_network_options` on multisite, `manage_options` on single site

## Common Pitfalls

- **Triggering promotions from the wrong branch.** Promote to Test must use `--ref dev`; Promote to Main must use `--ref test`. The workflows validate the branch and fail if it is wrong.
- **Workflow files missing from the ref branch.** If `gh workflow run "X" --ref dev` gives "does not have workflow_dispatch trigger", check that the workflow file exists on the `dev` branch. Promotion workflows must be on both `dev` and `main`.
- **Checking workflow state too soon.** GitHub takes 2-5 minutes to process workflow file changes. Wait before verifying.
- **Flat release zip.** The Promote to Main workflow must copy files into `/tmp/build/mxroute-mailer/` and zip that folder. Building inside the workspace creates a recursive copy error.
- **Tag not on latest main.** Promote to Main checks out `origin/main` before tagging so the tag points to the merge commit.
- **Version drift.** Do not manually bump patch versions. The Auto Bump Version workflow handles it. Only bump minor/major versions manually when needed.
- **Apt server deployment requires secrets.** The promotion workflows use `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, and `DEPLOY_USER` secrets for SSH access to the apt server.

## Documentation

- User-facing docs: `wiki/` directory and the [GitHub wiki](https://github.com/richardkentgates/mxroute-mailer/wiki)
- Contributor guidelines: `.github/CONTRIBUTING.md`
- Promotion directive: `PROMOTION.md`
- WordPress readme: `readme.txt`

## When to Ask the User

- Before deleting tags or releases
- Before changing branch protection rules
- Before adding new dependencies or external services
- Before modifying the promotion workflow constraints in `PROMOTION.md`
- If a release fails and you are unsure whether to bump or fix in place
