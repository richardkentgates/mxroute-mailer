=== MXRoute Mailer ===
Contributors: richardkentgates
Tags: email, smtp, mxroute, mail
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.3
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Route all WordPress email through MXRoute's HTTP API to bypass SMTP port blocks.

== Description ==

MXRoute Mailer replaces WordPress's default mail function with MXRoute's HTTP API, ensuring all email is sent through port 443. This bypasses Google Cloud and other hosting environments that block SMTP ports.

Features:
- Automatic email routing through MXRoute HTTP API
- Email logging with filtering and pagination
- Admin dashboard with send statistics
- Test email functionality with full API response
- Works with all WordPress plugins and themes

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/mxroute-mailer`
2. Activate the plugin through the Plugins screen
3. Go to Settings > MXRoute Mailer to configure

== Changelog ==

= 1.1.2 =
* Added branching strategy with dev/test/main workflow
* Added build artifacts for testing

= 1.1.1 =
* Added build jobs to promotion workflows

= 1.1.0 =
* Added CI/CD pipeline with quality gates
* Added GitHub Actions for testing and releases

= 1.0.0 =
* Initial release
