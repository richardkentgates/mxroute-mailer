# Promotion Workflow Directive

This document records the exact promotion workflow. Do not modify or expand these steps.

## Steps

1. **Push changes to `dev`.**
   - All development happens on `dev`.
   - `test` and `main` are promotion-only branches.

2. **All checks run on `dev`.**
   - PHP syntax lint
   - PHPUnit (PHP 7.3, 7.4, 8.0, 8.1, 8.2, 8.3)
   - zizmor workflow analysis
   - Semgrep PHP security scan
   - CodeQL analysis
   - Pinned-Actions enforcement
   - If any check fails, stop. Fix on `dev` and restart from step 1.

3. **Auto version bump.**
   - Every human push to `dev` triggers the Auto Bump Version workflow.
   - This increments the patch version in `mxroute-mailer.php` and pushes a `[version] [skip ci]` commit back to `dev`.

4. **Promote `dev` to `test`.**
   - Run the **Promote to Test** workflow from the `dev` branch:
     ```bash
     gh workflow run "Promote to Test" --repo richardkentgates/mxroute-mailer --ref dev
     ```
   - This workflow creates/merges a PR from `dev` to `test` and uploads a test artifact.
   - Green means promoted. Red means not promoted.

5. **Promote `test` to `main`.**
   - Run the **Promote to Main** workflow from the `test` branch:
     ```bash
     gh workflow run "Promote to Main" --repo richardkentgates/mxroute-mailer --ref test
     ```
   - This workflow creates/merges a PR from `test` to `main`, creates the release tag on `origin/main`, and triggers the Release workflow.
   - The Release workflow builds the zip with a top-level `mxroute-mailer/` folder and creates the GitHub release.
   - Checks are not re-run because `test` already contains only promoted, checked code.

## Constraints

- Nothing is manual except the explicit promotion/release triggers.
- `test` and `main` never receive direct changes.
- The workflow cannot be circumvented without locking yourself out of pushing or promoting.
- Do not add, remove, or reinterpret these steps.
