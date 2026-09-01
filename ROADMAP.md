# MXRoute Mailer Roadmap

Last updated 2026-09-01.

---

## What This Is

MXRoute Mailer is a WordPress plugin that routes outbound email through MXRoute's HTTPS API instead of SMTP. It is designed for hosting environments where outbound SMTP ports are blocked, and it supports multisite.

---

## Repository Overview

| Repo | Branch | Purpose |
|------|--------|---------|
| `MXR Mailer/mxroute-mailer` | dev/test/main | WordPress plugin source, CI/CD, GitHub Pages docs |

---

## Current Status

| Item | Value |
|------|-------|
| Production version | v1.4.59 |
| Dev version | tracks dev branch (CI auto-bump) |
| Distribution | Apt server `metadata.json` + GitHub releases |
| Apt server | 34.136.87.92 (`apt.richardkentgates.com`) |
| Pages | `mailer.richardkentgates.com` via j-make (gh-pages branch) |

---

## What's Done

### v1.4.59 — Charset Fix, WP-CLI Updater Fix, REST API (2026-09-01)

- **Charset fix**: Added `Content-Type: text/html; charset=UTF-8` to API email payload. The SMTP path set CharSet via PHPMailer but the API path sent body without charset, causing MXRoute to default to Latin-1. UTF-8 characters like bullets (•) were garbled as `â€¢`.
- **WP-CLI updater fix**: Removed WP-CLI skip from updater. The updater previously skipped `pre_set_site_transient_update_plugins` hook under WP-CLI, making plugins uninstallable via `wp plugin update`.
- **REST API**: `class-mxroute-rest-api.php` with `GET /mxroute/v1/status` endpoint, protected by WordPress Application Passwords.
- **Status JSON**: Writes to `/var/run/mxroute-status.json` during `process_queue()`.
- **GCM integration**: MU plugin reads MXRoute status JSON directly (no DB fallback).

### v1.4.55 — Dashboard, Security, Adopt Fixes

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

### v1.4.11 — Production

- Fixed WP-CLI activation hang by skipping HTTP hooks under WP-CLI.
- Apt server distribution via `metadata.json`.
- GCM integration with idle checks and queue monitoring:
  - `gcm_check_mxr_idle()` and `gcm_mxr_job_count()` in GCM CLI
  - Queue status included in `do_status()` JSON (`mxroute.busy`, `mxroute.pending_count`)
  - Backup, restore, dist-upgrade, and pending-queue processing all defer while MXRoute queue is active

### v1.4.12–v1.4.48 — CI Fixes, Test Channel, Audit Fixes

- CI workflow fixes for GitHub Actions workflow registration and YAML parsing.
- Added production deployment workflow.
- Added test channel support in `MXRoute_Updater`.
- Fixed CLI `queue clear` to use `MXRoute_Queue::clear_pending()` instead of raw SQL DELETE.
- Extracted recipient normalization into `MXRoute_API::sanitize_email_address()` static helper.

---

## Pipeline

```
dev ──push──> ci.yml (PHP lint + auto version bump)
    │
    │  workflow_dispatch: promote-to-test.yml
    ▼
test ──push──> promote-to-test.yml (build zip + deploy to apt server test channel)
    │
    │  workflow_dispatch: promote-to-main.yml
    ▼
main ──push──> promote-to-main.yml (tag + GitHub release + deploy to apt server production channel)
```

---

## Conventions

- All work on `dev` only. Never checkout/edit/push `test` or `main`.
- Promote via `workflow_dispatch` triggers only.
- CI auto-bumps version on every dev push — do not manually edit version numbers.
- WordPress coding standards: tabs, snake_case, Yoda conditions, full docblocks.
- Distribution is via apt server `metadata.json`; GCM installs/updates the plugin but does not activate it.

---

## What's Left

### HIGH — Status JSON + REST API with Application Passwords — ✅ DONE

**Goal:** MXRoute Mailer writes a status JSON file during queue processing. A new REST API endpoint serves this data to external consumers, protected by WordPress Application Passwords (Basic Auth). The GCM MU plugin reads the JSON file directly for the dashboard widget.

**Implemented:**
- ✅ `class-mxroute-rest-api.php` — REST route `GET /mxroute/v1/status` with Application Password auth
- ✅ `class-mxroute-queue.php` — writes status JSON to `/var/run/mxroute-status.json` during `process_queue()`
- ✅ GCM MU plugin reads MXRoute status JSON directly (no DB fallback)
- ✅ `Content-Type: text/html; charset=UTF-8` added to API email payload
- ✅ WP-CLI updater skip removed — plugins now updateable via `wp plugin update`

### MEDIUM — Audit #4 Cleanup

- [ ] **Remove vestigial `$attachments` parameter** from `send_via_api()`.
- [ ] **Add `error_log()` to `process_queue()`** failures when debug mode enabled.

### Maintenance
1. Keep GitHub Pages docs in sync with any future changes.
