# MXRoute Mailer — forward plan

**No history here.** Closed work lives in [`_archive/ROADMAP-HISTORY.md`](_archive/ROADMAP-HISTORY.md)
and `CHANGELOG.md`. Software versions are never recorded in docs — check
the repo or the releases.

## What this is

MXRoute Mailer is a WordPress plugin that routes outbound email through
MXRoute's HTTPS API instead of SMTP, for hosting environments where
outbound SMTP ports are blocked. It supports multisite and is the site
mail transport for client sites (`no-reply@<domain>`); see `AGENTS.md`
for the role, gating, and activation rules.

## Distribution

- Apt server `metadata.json` + GitHub releases (apt server
  `apt.richardkentgates.com`)
- Docs: `wiki/` is the source of truth; served at
  `mailer.richardkentgates.com` (gh-pages branch)

## Pipeline

```
dev   all development, direct push; ci.yml runs checks + auto version bump
  │   workflow_dispatch: promote-to-test.yml
  ▼
test  build zip + deploy to apt server test channel
  │   workflow_dispatch: promote-to-main.yml
  ▼
main  tag + GitHub release + deploy to apt server production channel
```

Promotion is `workflow_dispatch` only — no push-triggered promotion.
`PROMOTION.md` is the authoritative step list.

## Next

- [ ] Pinned-tree promote gates (`check-ci`) backport — pattern in
  `GITHUB-ACTIONS-RULES.md` (already in aptctl + admin-gui; owed here)
- [ ] Two-connection deploy pattern (apt-server UFW burst fix) port —
  fixed in metamanager-plugin 2026-11-13; owed here

## Standing

- Keep the GitHub Pages docs in sync when behavior changes — the
  `wiki/` → live wiki → gh-pages routine is in `AGENTS.md` →
  Documentation.
