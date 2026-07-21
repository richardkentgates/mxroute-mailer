# MXRoute Mailer

Route all WordPress email through MXRoute's HTTP API to bypass SMTP port blocks.

## Overview

MXRoute Mailer is a WordPress plugin that intercepts all emails sent via `wp_mail()` and routes them through MXRoute's HTTPS API (port 443) or SMTP — automatically choosing the best transport for each email. This solves the common problem of hosting providers blocking outbound SMTP ports (25, 465, 587), which causes WordPress emails to silently fail.

## Why Use MXRoute Mailer?

If your hosting provider blocks SMTP ports, standard WordPress email and SMTP plugins won't work. MXRoute Mailer uses MXRoute's HTTP API instead of SMTP, so email works on:

- Google Cloud Platform
- AWS (EC2, Lightsail)
- Shared hosting with restricted ports
- Any environment where SMTP is blocked

## Features

- **Smart-switch transport** - Automatically selects the best transport: API for lightweight emails, SMTP for emails with attachments (creates a copy in your MXRoute sent folder for legal records)
- **Email queue** - Background processing via a recurring WP-Cron event (every 60 seconds) with configurable batch size (1-50 emails per batch)
- **Persistent attachment storage** - Temp files are captured to persistent storage before sending; media library and plugin-provided files are referenced by native path/ID
- **Re-queue feature** - Resend any failed or sent email directly from the logs
- **Email logging** - Full logs with search, filtering, and pagination; transport method displayed in both list and detail views
- **WP-CLI commands** - Manage settings, logs, queue, and send emails from the command line
- **Multisite support** - Per-site settings and logs, network activate/deactivate, automatic table creation
- **Reply-To support** - Preserves form sender addresses as Reply-To headers
- **Test email** - Verify your configuration with a single click
- **Automatic updates** - Get new versions directly from GitHub with SHA-256 checksum verification
- **Developer-friendly** - Full CI/CD pipeline, coding standards, comprehensive tests (237 tests, 476 assertions)

## Requirements

- WordPress 5.0+
- PHP 7.3+
- A [MXRoute](https://mxroute.com) account with API credentials

## Quick Start

1. [Install the plugin](https://github.com/richardkentgates/mxroute-mailer/wiki/Installation)
2. [Configure your MXRoute credentials](https://github.com/richardkentgates/mxroute-mailer/wiki/Configuration)
3. Send a test email to verify everything works

## Documentation

- [Installation](https://github.com/richardkentgates/mxroute-mailer/wiki/Installation) - Step-by-step setup guide
- [Configuration](https://github.com/richardkentgates/mxroute-mailer/wiki/Configuration) - Server settings, credentials, logging
- [CLI Commands](https://github.com/richardkentgates/mxroute-mailer/wiki/CLI-Commands) - WP-CLI command reference
- [Troubleshooting](https://github.com/richardkentgates/mxroute-mailer/wiki/Troubleshooting) - Common issues and fixes
- [Auto-Updates](https://github.com/richardkentgates/mxroute-mailer/wiki/Auto-Updates) - How GitHub-based updates work
- [Development](https://github.com/richardkentgates/mxroute-mailer/wiki/Development) - Contributing, testing, CI/CD

Repository reference files:

- [README.md](https://github.com/richardkentgates/mxroute-mailer/blob/main/README.md) - Project overview and quick links
- [CONTRIBUTING.md](https://github.com/richardkentgates/mxroute-mailer/blob/main/.github/CONTRIBUTING.md) - Contribution guidelines
- [AGENTS.md](https://github.com/richardkentgates/mxroute-mailer/blob/main/AGENTS.md) - Guidance for AI agents and maintainers

## Support

- [GitHub Issues](https://github.com/richardkentgates/mxroute-mailer/issues) - Bug reports and feature requests
- [MXRoute](https://mxroute.com) - MXRoute hosting and API credentials

## License

GPL v2 or later - see [LICENSE](https://github.com/richardkentgates/mxroute-mailer/blob/main/LICENSE) for details.
