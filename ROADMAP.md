# MXRoute Mailer Roadmap

Last updated 2026-08-25.

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
| Production version | v1.4.48 |
| Dev version | v1.4.48 (current) |
| Distribution | Apt server `metadata.json` + GitHub releases |
| Apt server | 34.136.87.92 (`apt.richardkentgates.com`) |
| Pages | `mailer.richardkentgates.com` via j-make (gh-pages branch) |

---

## What's Done

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
test ──push──> test-deploy.yml (build zip + deploy to apt server test channel)
    │
    │  workflow_dispatch: promote-to-main.yml
    ▼
main ──push──> release.yml (tag + GitHub release + deploy to apt server production channel)
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

### Audit #4 — 2026-08-24 (Cross-Repo Audit) — RESOLVED

#### HIGH — All fixed
- ✅ **Remove dead `MXRoute_Logger::log()` method** — Still present, pending removal (see remaining items).
- ✅ **Fix CLI `queue clear` bypassing attachment cleanup** — Fixed: now uses `MXRoute_Queue::clear_pending()`.

#### MEDIUM — Partially resolved
- ✅ **Extract recipient normalization to helper** — Fixed: `MXRoute_API::sanitize_email_address()` replaces 4 copies.
- ✅ **Update ROADMAP.md version/status** — Fixed: v1.4.48.
- [ ] **Update readme.txt changelog** — Stops at v1.4.0, missing many releases.
- [ ] **Fix SECURITY.md** — Says "API key" but MXRoute uses account passwords; says "WordPress encryption" but actual implementation is AES-256-GCM.

#### LOW
- [ ] **Remove vestigial `$attachments` parameter** from `send_via_api()`.
- [ ] **Add `error_log()` to `process_queue()`** failures when debug mode enabled.
- [ ] **Remove dead `MXRoute_Logger::log()` method** — Lines 85–125, never called.

### Maintenance
1. Keep GitHub Pages docs in sync with any future changes.
