# MXRoute Mailer — Roadmap History (CLOSED)

**Retired 2026-11-16** — moved out of `ROADMAP.md` when it became a lean
forward plan. This is a record of completed work; nothing here is live.
Historical version numbers are kept as fix-citations only. The
status-JSON path citations were corrected to the verified path
(`wp-content/mxroute-status.json`) — the plugin briefly wrote
`/var/run/mxroute-status.json`, fixed to `WP_CONTENT_DIR` (commit
d87837c).

---

## Shipped — WP-Cron History Tracking, Dashboard Widget, Updater Fixes (2026-09-02 to 2026-09-03)

- **WP-Cron history tracking**: New `MXRoute_Cron_Tracker` class wraps cron callbacks to auto-track last run, next run, pass/fail counts, and last 20 runs per hook
- **Dashboard widget**: Registered via `wp_dashboard_setup`, shows queue stats and cron history table with status icons
- **inject_update fix**: `inject_update()` now returns `$transient` instead of `false` when no update available, preventing clobbering of other updaters' data
- **Updater transient cache removal**: Removed `TRANSIENT` and `CACHE_TTL` constants and all `get_transient`/`set_transient` calls from `get_metadata()`. Now fetches fresh from apt server every time, matching MetaManager's working pattern. Root cause documented in AGENTS.md.
- **`on_plugin_updated()`**: Re-registers `mxroute_mailer_process_queue`, `mxroute_write_status_json`, and `mxroute_mailer_daily_cleanup` crons after plugin update
- **Cron tracker registered in**: `mxroute-mailer.php`, `class-mxroute-mailer.php`
- **Status JSON**: New `history` key via `MXRoute_Cron_Tracker::get_all()`
- **CI tests updated**: Tests updated to match corrected `inject_update` return behavior
- **Production deployed**: v1.4.69 on all four servers

## Shipped — Charset Fix, WP-CLI Updater Fix, REST API (v1.4.59, 2026-09-01)

- **Charset fix**: Added `Content-Type: text/html; charset=UTF-8` to API email payload. The SMTP path set CharSet via PHPMailer but the API path sent body without charset, causing MXRoute to default to Latin-1. UTF-8 characters like bullets (•) were garbled as `â€¢`.
- **WP-CLI updater fix**: Removed WP-CLI skip from updater. The updater previously skipped `pre_set_site_transient_update_plugins` hook under WP-CLI, making plugins uninstallable via `wp plugin update`.
- **REST API**: `class-mxroute-rest-api.php` with `GET /mxroute/v1/status` endpoint, protected by WordPress Application Passwords.
- **Status JSON**: Writes to `wp-content/mxroute-status.json` during `process_queue()`.
- **GCM integration**: MU plugin reads MXRoute status JSON directly (no DB fallback).

## Shipped — Dashboard, Security, Adopt Fixes (v1.4.55)

- **wp-cron files missing from deb builds fixed**
- **Split-brain upgrade system removed** — `unattended-upgrades` removed
- **Dashboard widget refactor**: MU plugin queues widget reads MM daemon status JSONs instead of DB queries
- **ModEvasive thresholds raised**: DOSPageCount 15→30, DOSSiteCount 50→100
- **ModSecurity detection fixed**: symlink check instead of `apache2ctl -M`
- **Safe package upgrade added to adopt Phase 4**: patch-level updates only
- **DISABLE_WP_CRON injection**: adopt Phase 1b injects into wp-config.php
- **Status JSON PHP version fixed**: uses `gcm_apache_php_version()` instead of bare `php`
- **Removed `apt-get -s upgrade` from `do_status()`** — was holding apt lock
- **Removed `updates_available` and `reboot_required` from status JSON**
- **Fixed MySQL restart timing in adopt**: deferred to end

## Shipped — Production (v1.4.11)

- Fixed WP-CLI activation hang by skipping HTTP hooks under WP-CLI.
- Apt server distribution via `metadata.json`.
- GCM integration with idle checks and queue monitoring:
  - `gcm_check_mxr_idle()` and `gcm_mxr_job_count()` in GCM CLI
  - Queue status included in `do_status()` JSON (`mxroute.busy`, `mxroute.pending_count`)
  - Backup, restore, dist-upgrade, and pending-queue processing all defer while MXRoute queue is active

## Shipped — CI Fixes, Test Channel, Audit Fixes (v1.4.12–v1.4.48)

- CI workflow fixes for GitHub Actions workflow registration and YAML parsing.
- Added production deployment workflow.
- Added test channel support in `MXRoute_Updater`.
- Fixed CLI `queue clear` to use `MXRoute_Queue::clear_pending()` instead of raw SQL DELETE.
- Extracted recipient normalization into `MXRoute_API::sanitize_email_address()` static helper.

## Closed — Status JSON + REST API with Application Passwords

**Goal:** MXRoute Mailer writes a status JSON file during queue processing. A new REST API endpoint serves this data to external consumers, protected by WordPress Application Passwords (Basic Auth). The GCM MU plugin reads the JSON file directly for the dashboard widget.

**Implemented:**
- `class-mxroute-rest-api.php` — REST route `GET /mxroute/v1/status` with Application Password auth
- `class-mxroute-queue.php` — writes status JSON to `wp-content/mxroute-status.json` during `process_queue()`
- GCM MU plugin reads MXRoute status JSON directly (no DB fallback)
- `Content-Type: text/html; charset=UTF-8` added to API email payload
- WP-CLI updater skip removed — plugins now updateable via `wp plugin update`

## Closed — Audit #4 Cleanup (2026-11-07)

- **`$attachments` parameter on `send_via_api()`** — audited 2026-11-07,
  REJECTED as stated: the parameter is not vestigial. It is encoded into
  the API payload (`encode_attachments`) and the direct-transport tests
  assert that path. Removing it would delete working, tested behavior.
- **Add `error_log()` to `process_queue()`** failures when debug mode
  enabled — already implemented (`MXRoute Queue Failure` log guarded by
  `MXROUTE_MAILER_DEBUG`).
