# MXRoute Mailer Roadmap

Last updated 2026-08-10.

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
| Production version | v1.4.11 |
| Dev version | v1.4.19 (pending promotion) |
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

### v1.4.12–v1.4.19 (dev, pending promotion)

- CI workflow fixes for GitHub Actions workflow registration and YAML parsing.
- Added production deployment workflow.

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

### LOW — Maintenance

1. Keep GitHub Pages docs in sync with any future changes.
