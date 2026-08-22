# Changelog

All notable changes to MXRoute Mailer are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---









## [1.4.44] - 2026-08-22

### Changed
- Auto-incremented version from 1.4.43 to 1.4.44
## [1.4.43] - 2026-08-22

### Changed
- Auto-incremented version from 1.4.42 to 1.4.43
## [1.4.42] - 2026-08-20

### Changed
- Auto-incremented version from 1.4.41 to 1.4.42
## [1.4.41] - 2026-08-17

### Changed
- Auto-incremented version from 1.4.40 to 1.4.41
## [1.4.40] - 2026-08-15

### Changed
- Auto-incremented version from 1.4.39 to 1.4.40
## [1.4.39] - 2026-08-14

### Changed
- Auto-incremented version from 1.4.38 to 1.4.39
## [1.4.38] - 2026-08-14

### Changed
- Auto-incremented version from 1.4.37 to 1.4.38
## [1.4.37] - 2026-08-14

### Changed
- Auto-incremented version from 1.4.36 to 1.4.37
## [1.4.20] — Unreleased (dev)

### Changed

- CI workflow fixes for GitHub Actions workflow registration and YAML parsing.
- Added production deployment workflow.

---

## [1.4.11] — 2026-08-09

### Fixed

- WP-CLI activation hang by skipping HTTP hooks under WP-CLI.

### Added

- Apt server distribution via `metadata.json`.
- GCM integration with idle checks and queue monitoring.

---

## [1.4.0] — 2026-07-15

### Added

- **Multisite support** — per-site settings, logs, and cron; network activate/deactivate; per-site `keep_data` on uninstall.
- **WP-CLI commands** — manage settings, logs, queue, and send emails from the command line.
- **Internationalization** — text domain loaded, Domain Path header added, languages directory ready.

### Changed

- Multisite capability check — network admins see settings pages; helper function `mxroute_mailer_can_manage()`.
- Activation hook creates tables on all existing sites when network activated.
- New site initialization hook creates tables automatically on multisite.
- Uninstall handler cleans up all sites on multisite networks.
- Menu pages use correct capability for multisite (`manage_network_options`).

---

## [1.3.29] — 2026-07-10

### Fixed

- Resolve all WordPress Plugin Check errors — stable tag, i18n placeholders, file system functions.
- Test attachment writes use `wp_upload_dir` instead of plugin directory.
- Replaced `unlink()` with `wp_delete_file()`, `mkdir()` with `wp_mkdir_p()`.
- `WP_Filesystem` used for directory removal in uninstall handler.
- Added translators comments for all placeholder strings.
- Dashboard IN query uses prepared placeholders directly.

### Tests

- 237 tests passing, zero Plugin Check ERRORs.

---

## [1.3.26] — 2026-07-08

### Security

- Crypto `encrypt()` returns `WP_Error` instead of plaintext when OpenSSL unavailable.
- Updater rejects updates missing `.sha256` checksum asset.
- Added nonce verification to log detail page.
- Updater uses `wp_safe_remote_get` for GitHub API calls.

### Fixed

- Debug logging no longer resets `json_last_error`, preventing silent JSON decode failures.
- Redacted username, server, port, and encryption from debug logs.

### Changed

- Singleton reset for proper test isolation.

### Tests

- 237 tests passing across PHP 7.3–8.3.

---

## [1.3.22] — 2026-07-05

### Fixed

- AJAX action hooks use correct WordPress `wp_ajax_` prefix — requeue, delete, clear logs, and queue check now work.

---

## [1.3.21] — 2026-07-04

### Fixed

- Requeue operation uses single SQL query for atomicity.

---

## [1.3.20] — 2026-07-03

### Fixed

- Bulk requeue and delete operations use individual method calls for reliability.

---

## [1.3.19] — 2026-07-02

### Changed

- Queue processor runs on a recurring 60-second cron cycle instead of per-email scheduling.

---

## [1.3.17] — 2026-07-01

### Fixed

- Stored temp attachments use stored copy only — no fallback to originating software's temp path.

### Changed

- Stored attachments kept after successful send for re-queue capability.

---

## [1.3.14] — 2026-06-28

### Changed

- Transport column shown in logs list view (API or SMTP).
- Queue page auto-refreshes every 10 seconds — processed rows fade out automatically.

---

## [1.3.13] — 2026-06-27

### Fixed

- Transport always computed from actual attachments instead of stored value.
- Remove duplicate log rows — queue entry IS the log entry, no extra row inserted.

### Changed

- Queue page polling checks pending status of visible rows.

---

## [1.3.10] — 2026-06-25

### Fixed

- Load PHPMailer classes before SMTP use — prevents fatal when WordPress hasn't autoloaded them.
- Catch `\Throwable` instead of `\Exception` in `process_queue` to handle PHP 7+ class-not-found errors.

### Changed

- Test email sends all three attachment types (media library ID, persistent path, temp file).
- Each test attachment type uses a distinct file so all three arrive as separate attachments.

---

## [1.3.8] — 2026-06-24

### Fixed

- SMTP fatal error when PHPMailer class not loaded — added `require_once` for PHPMailer, SMTP, and Exception.
- Queue processor now catches all errors including `\Error` (not just `\Exception`).

---

## [1.3.4] — 2026-06-22

### Changed

- Attachment storage is now smart — only temp files are copied to persistent storage; media library and plugin files are referenced by native path/ID.
- Log detail page shows attachment type, original path, and storage status (OK/Missing/N/A).
- Queue page shows attachment count and storage status per entry.
- Test email goes through the queue (mirrors real sending path).
- Test email response shows "Queued" status with cron processing info.

### Fixed

- `cleanup()` now removes orphaned stored attachments when purging old log entries.
- `resolve_attachments()` properly handles all three reference types (id, path, stored).
- Missing `MB_IN_BYTES` constant in test environment.
- `MockWPDB` now includes `wp_upload_dir`, `wp_basename`, and `get_col` mocks.

### Tests

- All 229 tests passing.

---

## [1.3.3] — 2026-06-21

### Fixed

- SMTP smart switch now correctly retries all ports (465, 587, 2525) on failure.
- Failed email logs now visible on logs page (stored as status -1 instead of 0).
- Race condition in queue processing — atomic row claiming prevents duplicate sends.
- Cron exceptions caught per-item so one failure doesn't block the batch.
- Batch size default consistent (5) across settings and processor.
- Batch size sanitizer capped at 50 to match UI.
- `delete_log` verifies log exists before deleting.
- Bulk delete filters out negative IDs.

### Changed

- Queue page uses shared query method instead of duplicating SQL.

---

## [1.3.0] — 2026-06-18

### Added

- Email queue with background processing via WP-Cron.
- File attachment support for all email types.
- Re-queue feature to resend failed or sent emails.
- Dedicated queue status page.

### Changed

- Pending emails hidden from logs page (view on queue page).
- Dynamic row removal on re-queue (no page reload).

### Security

- Separate access between logs and queue views.

---

## [1.2.21] — 2026-06-15

### Fixed

- Remove dead `drop_table()` and `get_recent_logs()` methods from logger.
- Remove obsolete v1.2.17 regression test.
- Remove duplicate `get_option` call in `intercept_wp_mail`.
- Add `mxroute_mailer_db_version` to uninstall cleanup.

### Security

- Remove password-leaking `error_log()` calls from API client.
- Gate debug logging behind `MXROUTE_MAILER_DEBUG` constant.

---

## [1.2.20] — 2026-06-14

### Fixed

- Prevent the password-encryption filter from double-encrypting an already encrypted password.

---

## [1.2.19] — 2026-06-13

### Security

- Encrypt the stored MXRoute password with AES-256-GCM.
- Sanitize From, To, and Reply-To email addresses before sending to the MXRoute API.
- Verify release zip checksums during automatic updates.

---

## [1.2.16] — 2026-06-10

### Fixed

- Fix duplicate sends by using the `pre_wp_mail` filter to short-circuit WordPress's default mailer.
- Return `true` after a successful MXRoute API send and `false` after a failure.

---

## [1.2.0] — 2026-06-01

### Added

- GitHub-based automatic updates.

### Fixed

- Audit fixes: critical bugs, security hardening, settings alignment.
- Username field now derives domain from WordPress site URL.
- Test email uses configured username as sender address.
- Fixed stored XSS vulnerability in email log viewer.
- Fixed `intercept_wp_mail` to fall back to WP mailer on API failure.
- Removed debug logging from production code.

---

## [1.1.0] — 2026-05-15

### Added

- CI/CD pipeline with quality gates.
- GitHub Actions for testing and releases.
- Branching strategy with dev/test/main workflow.
- Build artifacts for testing.

---

## [1.0.0] — 2026-05-01

### Added

- Initial release.
- MXRoute HTTP API integration for sending email.
- Admin settings page with credentials configuration.
- Test email functionality.
- Email logging with search and filtering.
