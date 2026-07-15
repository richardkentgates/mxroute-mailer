# MXRoute Mailer

Route all WordPress email through MXRoute's HTTP API to bypass SMTP port blocks.

## Overview

MXRoute Mailer is a WordPress plugin that intercepts all emails sent via `wp_mail()` and routes them through MXRoute's HTTPS API (port 443). This solves the common problem of hosting providers blocking outbound SMTP ports (25, 465, 587), which causes WordPress emails to silently fail.

## Why Use MXRoute Mailer?

If your hosting provider blocks SMTP ports, standard WordPress email and SMTP plugins won't work. MXRoute Mailer uses MXRoute's HTTP API instead of SMTP, so email works on:

- Google Cloud Platform
- AWS (EC2, Lightsail)
- Shared hosting with restricted ports
- Any environment where SMTP is blocked

## Features

- **Automatic email routing** - Intercepts `wp_mail()` and sends through MXRoute API
- **Email logging** - Full logs with search, filtering, and pagination
- **Reply-To support** - Preserves form sender addresses as Reply-To headers
- **Test email** - Verify your configuration with a single click
- **Automatic updates** - Get new versions directly from GitHub
- **Developer-friendly** - Full CI/CD pipeline, coding standards, comprehensive tests

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
- [Troubleshooting](https://github.com/richardkentgates/mxroute-mailer/wiki/Troubleshooting) - Common issues and fixes
- [Auto-Updates](https://github.com/richardkentgates/mxroute-mailer/wiki/Auto-Updates) - How GitHub-based updates work
- [Development](https://github.com/richardkentgates/mxroute-mailer/wiki/Development) - Contributing, testing, CI/CD

## Support

- [GitHub Issues](https://github.com/richardkentgates/mxroute-mailer/issues) - Bug reports and feature requests
- [MXRoute](https://mxroute.com) - MXRoute hosting and API credentials

## License

GPL v2 or later - see [LICENSE](LICENSE) for details.
