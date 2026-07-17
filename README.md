# MXRoute Mailer

Route all WordPress email through MXRoute's HTTP API to bypass SMTP port blocks.

## Overview

MXRoute Mailer is a WordPress plugin that intercepts all emails sent via `wp_mail()` and routes them through MXRoute's HTTPS API on port 443. It solves the common problem of hosting providers blocking outbound SMTP ports (25, 465, 587), which causes WordPress emails to silently fail.

## Features

- Automatic email routing through MXRoute's HTTP API
- Email queue with background processing via WP-Cron
- File attachment support
- Re-queue feature for failed or sent emails
- Email logging with search, filtering, and pagination
- Reply-To header support for contact forms
- Built-in test email tool
- GitHub-based automatic updates
- Works with any plugin or theme that uses `wp_mail()`

## Requirements

- WordPress 5.0 or higher
- PHP 7.3 or higher
- A [MXRoute](https://mxroute.com) account with API credentials

## Quick Start

1. Download the latest release from [GitHub Releases](https://github.com/richardkentgates/mxroute-mailer/releases)
2. Install and activate the plugin in WordPress
3. Go to **Settings > MXRoute Mailer** and enter your MXRoute credentials
4. Send a test email to verify the configuration

## Documentation

- [Installation](https://github.com/richardkentgates/mxroute-mailer/wiki/Installation)
- [Configuration](https://github.com/richardkentgates/mxroute-mailer/wiki/Configuration)
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
