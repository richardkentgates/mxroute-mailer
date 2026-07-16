=== MXRoute Mailer ===
Contributors: richardkentgates
Tags: email, smtp, mxroute, mail
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.3
Stable tag: 1.2.20
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Route all WordPress email through MXRoute's HTTP API with logging and automatic updates.

== Description ==

MXRoute Mailer replaces WordPress's default mail function with MXRoute's HTTP API, ensuring all email is sent through port 443. This bypasses Google Cloud and other hosting environments that block SMTP ports.

**Why use MXRoute Mailer?**

If your hosting provider blocks outbound SMTP ports (common on Google Cloud, AWS, and shared hosting), WordPress emails -- password resets, contact form submissions, notifications -- silently fail. MXRoute Mailer solves this by sending email through MXRoute's HTTPS API instead.

**Features:**

* Automatic email routing through MXRoute HTTP API (port 443)
* Email logging with filtering, search, and pagination
* Reply-To header support for contact forms
* Test email functionality with full API response details
* GitHub-based automatic updates
* Works with all WordPress plugins that use `wp_mail()`
* Developer-friendly with full CI/CD pipeline and coding standards

**Requirements:**

* A [MXRoute](https://mxroute.com) hosting account with API credentials
* WordPress 5.0 or higher
* PHP 7.3 or higher

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/mxroute-mailer`
2. Activate the plugin through the Plugins screen in WordPress
3. Go to **Settings > MXRoute Mailer** to configure your MXRoute credentials
4. Enter your MXRoute server hostname, username, and password
5. Use the **Send Test Email** form to verify your configuration

== Frequently Asked Questions ==

= What is MXRoute? =

MXRoute is an email hosting provider that offers SMTP and HTTP API access for sending email. Their API works over HTTPS (port 443), making it ideal for environments where traditional SMTP ports are blocked.

= How does this plugin differ from other SMTP plugins? =

Most SMTP plugins require outbound port access (587, 465, 25). MXRoute Mailer uses MXRoute's HTTP API over port 443, so it works on hosting environments that block SMTP traffic -- including Google Cloud, AWS, and many shared hosts.

= Will this work with my contact form plugin? =

Yes. MXRoute Mailer intercepts all calls to `wp_mail()` and routes them through MXRoute. Any plugin that uses `wp_mail()` -- Contact Form 7, WPForms, Gravity Forms, Ninja Forms, and others -- will work automatically.

= How are Reply-To headers handled? =

If a plugin sets a `From` header (like a contact form submitting a user's email), the plugin preserves that address as a `Reply-To` header. The `From` address always uses your configured MXRoute username to ensure deliverability.

= Where are the email logs? =

Go to **Tools > MXRoute Logs** in your WordPress admin. You can search, filter by date/status/sender, and view full API request/response details for each email.

= Does this send through SMTP or API? =

API. The plugin sends email through MXRoute's HTTPS endpoint (`https://smtpapi.mxroute.com/`), not SMTP. This is why it works when SMTP ports are blocked.

= How do automatic updates work? =

The plugin checks GitHub releases for new versions. When an update is available, it appears in your WordPress dashboard like any other plugin update. See the [Auto-Updates wiki page](https://github.com/richardkentgates/mxroute-mailer/wiki/Auto-Updates) for details.

= What happens if the MXRoute API is down? =

The plugin fires a `wp_mail_failed` action so other plugins can handle the failure. WordPress falls back to its default mailer, so your site doesn't silently lose email.

== Screenshots ==

1. Settings page with MXRoute credentials and test email form
2. Email logs list with search, filtering, and pagination
3. Individual log detail view with API request and response data

== Changelog ==

= 1.2.20 =
* Fix: prevent the password-encryption filter from double-encrypting an already encrypted password

= 1.2.19 =
* Security: encrypt the stored MXRoute password with AES-256-GCM
* Security: sanitize From, To, and Reply-To email addresses before sending to the MXRoute API
* Security: verify release zip checksums during automatic updates
* Update documentation to reflect security improvements

= 1.2.17 =
* Update help tabs with documentation links and duplicate-send guidance
* Refresh user and developer documentation

= 1.2.16 =
* Fix duplicate sends by using the `pre_wp_mail` filter to short-circuit WordPress's default mailer
* Return `true` after a successful MXRoute API send and `false` after a failure

= 1.2.14 =
* Fix release zip build to avoid workspace self-copy failure
* Clean top-level `mxroute-mailer/` folder in release asset

= 1.2.13 =
* Build release zip with a top-level `mxroute-mailer/` folder so WordPress extracts into the correct plugin directory

= 1.2.12 =
* Remove composer dependency; use PHPUnit PHAR and PHP syntax lint
* Replace OSSF Scorecard with zizmor, Semgrep, CodeQL, and pinned-action checks
* Auto-bump patch version on human pushes to `dev`
* Harden CI security checks

= 1.2.11 =
* Pin Semgrep action SHA and tighten unpinned-action check
* Fix zizmor SARIF output redirection

= 1.2.10 =
* Harden Scorecard replacement job with zizmor and pinned-action checks
* Use correct setup-python action reference

= 1.2.9 =
* Replace OSSF Scorecard with dev-branch-compatible security checks
* Move security checks to CI on `dev` push only

= 1.2.8 =
* Add `workflow_dispatch` trigger to Release workflow
* Remove legacy Promote workflow and trigger Release after tag creation
* Prevent WordPress from also sending via server mailer when plugin is configured
* Update tests to expect the `wp_mail` filter to return false when configured

= 1.2.7 =
* Split promotion into Promote to Test and Promote to Main workflows
* Skip tag creation if release tag already exists

= 1.2.6 =
* Add Promote to Test and Promote to Main workflows

= 1.2.5 =
* Formalize PR-based promotion workflow
* Consolidate CI/CD from 7 workflow files to 4
* Add branch protection on test and main branches
* Fix CodeQL workflow to analyze GitHub Actions instead of unsupported PHP
* Update Scorecard action SHA to fix imposter commit error

= 1.2.4 =
* Always use plugin username as From address in logs and API calls
* Set form-provided From address as Reply-To header
* Add reply_to column to email logs table with automatic DB migration
* Display Reply-To in log detail view when present

= 1.2.3 =
* Strip display names from recipient addresses in log storage

= 1.2.2 =
* Strip display names from recipient addresses (e.g. "Name <email>" to just email)

= 1.2.1 =
* Fix username sanitizer to strip full email addresses entered in local-part field

= 1.2.0 =
* Added GitHub-based automatic updates
* Audit fixes: critical bugs, security hardening, settings alignment
* Username field now derives domain from WordPress site URL
* Test email uses configured username as sender address
* Fixed stored XSS vulnerability in email log viewer
* Fixed intercept_wp_mail to fall back to WP mailer on API failure
* Removed debug logging from production code

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

== Upgrade Notice ==

= 1.2.17 =
Documentation and help tab updates. No functional changes.

= 1.2.16 =
Fixes duplicate email sends. WordPress will no longer also send through the server mailer when MXRoute Mailer is configured.

= 1.2.14 =
Release packaging fix. WordPress now installs updates into the correct `mxroute-mailer/` folder.

= 1.2.8 =
Plugin now prevents WordPress from also sending via server mailer when configured.

= 1.2.5 =
CI/CD improvements. No functional changes.

= 1.2.4 =
From address now always uses your MXRoute username. Form sender addresses are preserved as Reply-To.

= 1.2.0 =
Adds automatic updates from GitHub. Recommended update for all users.

== Support ==

* [GitHub Issues](https://github.com/richardkentgates/mxroute-mailer/issues) - Bug reports and feature requests
* [Wiki](https://github.com/richardkentgates/mxroute-mailer/wiki) - Documentation and guides
* [MXRoute](https://mxroute.com) - MXRoute hosting and API credentials

== Credits ==

Built for the MXRoute community. MXRoute provides reliable, affordable email hosting with HTTP API access.
