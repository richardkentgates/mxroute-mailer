=== MXRoute Mailer ===
Contributors: richardkentgates
Tags: email, smtp, mxroute, mail
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.3
Stable tag: 1.3.22
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Route all WordPress email through MXRoute's HTTP API with logging and automatic updates.

== Description ==

MXRoute Mailer replaces WordPress's default mail function with MXRoute's HTTP API, ensuring all email is sent through port 443. This bypasses Google Cloud and other hosting environments that block SMTP ports.

**Why use MXRoute Mailer?**

If your hosting provider blocks outbound SMTP ports (common on Google Cloud, AWS, and shared hosting), WordPress emails -- password resets, contact form submissions, notifications -- silently fail. MXRoute Mailer solves this by sending email through MXRoute's HTTPS API instead.

**Features:**

* Automatic email routing through MXRoute HTTP API (port 443)
* Email queue with background processing via WP-Cron
* File attachment support for all email types
* Re-queue feature to resend failed or sent emails
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

Go to **Tools > MXRoute Logs** in your WordPress admin. You can search, filter by date/status/sender, and view full API request/response details for each email. Pending emails in the queue are shown separately under **Tools > MXRoute Queue**.

= What is the email queue? =

The plugin queues all outgoing emails and processes them in the background via a recurring WP-Cron event that runs every 60 seconds. Emails are sent in configurable batches (1-50 per cycle). This ensures reliable delivery even during high-traffic periods. You can view pending emails on the Queue page and re-queue any sent or failed email from the Logs page.

= Does this send through SMTP or API? =

Both. The plugin uses a smart switch to choose the best transport for each email. Emails without attachments are sent via the MXRoute HTTP API (lightweight, no server storage). Emails with attachments are sent via SMTP, which creates a copy in your MXRoute sent folder — useful for legal records and documentation. The transport is selected automatically and logged in the email details.

= Does this support file attachments? =

Yes. The plugin supports file attachments in all outgoing emails. Emails with attachments are automatically routed via SMTP (PHPMailer) because the MXRoute HTTP API does not support attachments. This also creates a copy in your MXRoute sent folder for record-keeping. The transport method (API or SMTP) is logged and visible in the email log details.

= How do automatic updates work? =

The plugin checks GitHub releases for new versions. When an update is available, it appears in your WordPress dashboard like any other plugin update. See the [Auto-Updates wiki page](https://github.com/richardkentgates/mxroute-mailer/wiki/Auto-Updates) for details.

= What happens if an email fails? =

Failed emails are logged with status -1 and visible on the Logs page. You can re-queue any failed email to try again. The plugin does not automatically retry — it logs the failure so you can review and take action.

== Screenshots ==

1. Settings page with MXRoute credentials and test email form
2. Email logs list with search, filtering, and pagination
3. Individual log detail view with API request and response data

== Changelog ==

= 1.3.22 =
* Fix: AJAX action hooks use correct WordPress `wp_ajax_` prefix — requeue, delete, clear logs, and queue check now work

= 1.3.21 =
* Fix: Requeue operation uses single SQL query for atomicity

= 1.3.20 =
* Fix: Bulk requeue and delete operations use individual method calls for reliability

= 1.3.19 =
* Improvement: Queue processor runs on a recurring 60-second cron cycle instead of per-email scheduling

= 1.3.18 =
* Documentation update for v1.3.16

= 1.3.16 =
* Fix: Stored temp attachments use stored copy only — no fallback to originating software's temp path
* Improvement: Stored attachments kept after successful send for re-queue capability

= 1.3.15 =
* Fix: Stored column for media library attachments now resolves file path and checks existence

= 1.3.14 =
* Improvement: Transport column shown in logs list view (API or SMTP)
* Improvement: Queue page auto-refreshes every 10 seconds — processed rows fade out automatically

= 1.3.13 =
* Fix: Transport always computed from actual attachments instead of stored value
* Fix: Remove duplicate log rows — queue entry IS the log entry, no extra row inserted
* Improvement: Queue page polling checks pending status of visible rows

= 1.3.12 =
* Documentation update for v1.3.10

= 1.3.11 =
* Fix: Test email media library attachment uses its own distinct file

= 1.3.10 =
* Fix: Load PHPMailer classes before SMTP use — prevents fatal when WordPress hasn't autoloaded them
* Fix: Catch `\Throwable` instead of `\Exception` in process_queue to handle PHP 7+ class-not-found errors
* Improvement: Test email sends all three attachment types (media library ID, persistent path, temp file)
* Improvement: Each test attachment type uses a distinct file so all three arrive as separate attachments

= 1.3.8 =
* Fix: SMTP fatal error when PHPMailer class not loaded — added require_once for PHPMailer, SMTP, and Exception
* Fix: Queue processor now catches all errors including `\Error` (not just `\Exception`)

= 1.3.4 =
* Improvement: Attachment storage is now smart — only temp files are copied to persistent storage; media library and plugin files are referenced by native path/ID
* Improvement: Log detail page shows attachment type, original path, and storage status (OK/Missing/N/A)
* Improvement: Queue page shows attachment count and storage status per entry
* Improvement: Test email goes through the queue (mirrors real sending path)
* Improvement: Test email response shows "Queued" status with cron processing info
* Fix: cleanup() now removes orphaned stored attachments when purging old log entries
* Fix: resolve_attachments() properly handles all three reference types (id, path, stored)
* Fix: Missing MB_IN_BYTES constant in test environment
* Fix: MockWPDB now includes wp_upload_dir, wp_basename, and get_col mocks
* Tests: All 229 tests passing

= 1.3.3 =
* Fix: SMTP smart switch now correctly retries all ports (465, 587, 2525) on failure
* Fix: Failed email logs now visible on logs page (stored as status -1 instead of 0)
* Fix: Race condition in queue processing — atomic row claiming prevents duplicate sends
* Fix: Cron exceptions caught per-item so one failure doesn't block the batch
* Fix: Batch size default consistent (5) across settings and processor
* Fix: Batch size sanitizer capped at 50 to match UI
* Fix: delete_log verifies log exists before deleting
* Fix: Bulk delete filters out negative IDs
* Improvement: Queue page uses shared query method instead of duplicating SQL
* Cleanup: Remove unused localized strings, dead code, duplicate tests

= 1.3.2 =
* Fix: Test email attachment checkbox properly inside form with nonce
* Fix: Settings page form restored with all fields
* Fix: SMTP port retry properly clears Reply-To headers between attempts
* Fix: PHPMailer mock always regenerated to prevent stale cache

= 1.3.1 =
* Fix: Remove automatic re-scheduling of failed queue items — admin reviews and manually re-queues
* Fix: process_queue uses atomic claim to prevent concurrent duplicate processing

= 1.3.0 =
* Feature: Add email queue with background processing via WP-Cron
* Feature: Add file attachment support for all email types
* Feature: Add re-queue feature to resend failed or sent emails
* Feature: Add dedicated queue status page
* Improvement: Pending emails hidden from logs page (view on queue page)
* Improvement: Dynamic row removal on re-queue (no page reload)
* Security: Separate access between logs and queue views

= 1.2.21 =
* Fix: remove dead `drop_table()` and `get_recent_logs()` methods from logger
* Fix: remove obsolete v1.2.17 regression test
* Fix: remove duplicate `get_option` call in `intercept_wp_mail`
* Fix: add `mxroute_mailer_db_version` to uninstall cleanup
* Security: remove password-leaking `error_log()` calls from API client
* Security: gate debug logging behind `MXROUTE_MAILER_DEBUG` constant
* Cleanup: remove unused test mocks and redundant activation hook require
* Update documentation with debugging guidance

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

= 1.3.22 =
Fixes requeue, delete, and queue check buttons not working. Recommended update for all users.

= 1.3.16 =
Stored temp attachments now use stored copy only — no fallback. Stored attachments survive send for re-queue.

= 1.3.14 =
Transport column in logs list view. Queue page auto-refreshes with processed row fade-out.

= 1.3.13 =
Fixes duplicate log rows and ensures transport is always computed from actual attachments.

= 1.3.10 =
PHPMailer classes loaded before SMTP use. Test email exercises all three attachment types.

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
