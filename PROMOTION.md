# Promotion Workflow Directive

This document records the exact promotion workflow. Do not modify or expand these steps.

## Steps

1. **Push changes to `dev`.**
   - All development happens on `dev`.
   - `test` and `main` are promotion-only branches.

2. **All checks run on `dev`.**
   - PHPCS
   - PHPUnit (PHP 7.3, 7.4, 8.0, 8.1, 8.2, 8.3)
   - CodeQL
   - Scorecard
   - If any check fails, stop. Fix on `dev` and restart from step 1.

3. **If green, promote `dev` to `test`.**
   - Run the **Promote to Test** workflow from the `dev` branch.
   - This workflow merges `dev` into `test` and builds the test artifact.
   - Green means promoted. Red means not promoted.

4. **If green, promote `test` to `main`.**
   - Run the **Promote to Main** workflow from the `test` branch.
   - This workflow merges `test` into `main` and creates the release tag.
   - The tag automatically triggers the release workflow.
   - Checks are not re-run because `test` already contains only promoted, checked code.

## Constraints

- Nothing is manual except the explicit promotion/release triggers.
- `test` and `main` never receive direct changes.
- The workflow cannot be circumvented without locking yourself out of pushing or promoting.
- Do not add, remove, or reinterpret these steps.
