# MXRoute Mailer

[![Version](https://img.shields.io/github/v/release/richardkentgates/mxroute-mailer?label=Version&color=0073aa)](https://github.com/richardkentgates/mxroute-mailer/releases/latest)
[![License](https://img.shields.io/github/license/richardkentgates/mxroute-mailer?label=License&color=0073aa)](https://github.com/richardkentgates/mxroute-mailer/blob/main/LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.3+-777bb4?logo=php)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-5.0+-0073aa?logo=wordpress)](https://wordpress.org/)
[![CI](https://img.shields.io/github/actions/workflow/status/richardkentgates/mxroute-mailer/ci.yml?branch=dev&label=CI)](https://github.com/richardkentgates/mxroute-mailer/actions/workflows/ci.yml)

Route all WordPress email through MXRoute's API — HTTP for simple emails, SMTP for attachments.

## Overview

MXRoute Mailer is a WordPress plugin that intercepts all emails sent via `wp_mail()` and routes them through MXRoute's API. Emails without attachments are sent via MXRoute's HTTP API, while emails with attachments are sent via SMTP. It works on any hosting environment, including those where outbound SMTP ports are blocked.

## Features

- **Smart-switch transport** — Automatically selects API (port 443) for lightweight emails or SMTP for emails with attachments
- **Email queue** — Background processing via recurring WP-Cron (every 60 seconds) with configurable batch size
- **File attachment support** — Media library IDs, persistent paths, and temp files with intelligent storage
- **Re-queue feature** — Resend any failed or sent email directly from the logs
- **Email logging** — Full logs with search, filtering, pagination, and transport details
- **Reply-To support** — Preserves form sender addresses as Reply-To headers
- **WP-CLI commands** — Manage settings, logs, queue, and send emails from the command line
- **Multisite support** — Per-site settings and logs, network activate/deactivate
- **Built-in test email tool** — Verify configuration with a single click
- **Automatic updates** — GitHub-based updater with SHA-256 checksum verification
- **Works with any plugin or theme** that uses `wp_mail()`

## Requirements

- WordPress 5.0 or higher
- PHP 7.3 or higher
- A [MXRoute](https://mxroute.com) account with API credentials

## Quick Start

1. Download the latest release from [GitHub Releases](https://github.com/richardkentgates/mxroute-mailer/releases)
2. Install and activate the plugin in WordPress
3. Go to **Settings > MXRoute Mailer** and enter your MXRoute credentials
4. Send a test email to verify the configuration

## WP-CLI

MXRoute Mailer includes WP-CLI commands for command-line management:

```bash
# View all settings
wp mxroute option get

# Get a specific setting
wp mxroute option get server

# Update a setting
wp mxroute option set batch_size 10

# List recent logs
wp mxroute logs list

# View a specific log
wp mxroute logs view 123

# Check queue status
wp mxroute queue count

# Send an email directly
wp mxroute send user@example.com "Subject" "Body"

# Send a test email through the queue
wp mxroute test user@example.com
```

See the [CLI documentation](https://github.com/richardkentgates/mxroute-mailer/wiki/CLI-Commands) for full details.

## Multisite

MXRoute Mailer supports WordPress Multisite:

- **Network activation** — Activate once, available on all sites
- **Per-site settings** — Each site has its own MXRoute credentials and logging
- **Per-site logs** — Email logs are isolated per site
- **Automatic table creation** — New sites get the logs table automatically
- **Network admin access** — Site admins with `manage_network_options` capability can access settings

## Documentation

- [Installation](https://github.com/richardkentgates/mxroute-mailer/wiki/Installation)
- [Configuration](https://github.com/richardkentgates/mxroute-mailer/wiki/Configuration)
- [CLI Commands](https://github.com/richardkentgates/mxroute-mailer/wiki/CLI-Commands)
- [Troubleshooting](https://github.com/richardkentgates/mxroute-mailer/wiki/Troubleshooting)
- [Auto-Updates](https://github.com/richardkentgates/mxroute-mailer/wiki/Auto-Updates)
- [Development](https://github.com/richardkentgates/mxroute-mailer/wiki/Development)

Local copies of the wiki pages are also kept in the [`wiki/`](wiki/) directory.

## Contributing

See [CONTRIBUTING.md](.github/CONTRIBUTING.md) and [AGENTS.md](AGENTS.md) for the branch workflow, coding standards, and AI-specific guidance.

## Support

- [GitHub Issues](https://github.com/richardkentgates/mxroute-mailer/issues)
- [MXRoute](https://mxroute.com)

## License

GPL v2 or later. See [LICENSE](LICENSE) for details.
