# Validation Quickstart: CoreX Product Functional Completion

This guide is the runnable acceptance entry point. A green narrow test is evidence only for the behavior it covers; final completion also requires the traceability and rendered matrices.

## Prerequisites

1. Work from `C:\wamp64\www\corex` on `fix/067-admin-shell-and-completion`.
2. Confirm the normal root is the only active worktree.
3. Confirm WordPress 7.0 boots and recognizes the CoreX theme and plugins.
4. Start Apache/MySQL through the documented WAMP path when browser tests require HTTP.

```powershell
git status --short --branch
git worktree list
wp --path=wp core version
wp --path=wp theme list --fields=name,status,version
wp --path=wp plugin list --fields=name,status,version
wp --path=wp eval "echo 'BOOT_OK';"
wp --path=wp corex doctor
```

Expected: WordPress `7.0`; CoreX theme active; `corex-core`, `corex-blocks`, `corex-config`, and `corex-forms` active; `BOOT_OK`; no PHP fatal.

## Focused TDD Loop

Run the smallest new test first, then the affected package suite:

```powershell
vendor\bin\pest tests\Unit\Insights\InsightWidgetsTest.php
vendor\bin\pest --filter InsightWidgets
npm.cmd run test:js -- --runInBand plugins/corex-config/src/insights/__tests__/insightsClient.test.js
```

Expected before implementation: the new assertion fails for the intended missing behavior. Expected after implementation: focused test and affected suite pass.

## End-to-End Scenario A — Flow to Inbox

1. Create a draft flow with text, email, consent, select, and step fields.
2. Add required/email/pattern validation.
3. Add first-match routing with a fallback.
4. Bind confirmation and team-notification templates.
5. Configure inline success and publish.
6. Insert a Flow block on a page.
7. Submit invalid input and verify accessible errors.
8. Submit valid input in Development.
9. Verify stored submission, assignment, captured emails, Inbox row, detail metadata, consent/version snapshot, and complete timeline.
10. Run test mode and verify exclusion from ordinary metrics/exports.

Evidence: browser recording/screenshots, submission/timeline records, captured email attempts, Pest integration test, Playwright test.

## End-to-End Scenario B — Submission Operations

1. Search and filter by flow, status, owner, and date.
2. Select multiple rows and preview/apply mark-read and assign.
3. Add a note and update one status.
4. Resend a related captured email.
5. Export selected rows with consent and notes.
6. Verify export artifact, history, personal-data warning, activity event, and permission scoping.

## End-to-End Scenario C — Data Import and Migration

1. Register a write-capable test source and a read-only source.
2. Prove action visibility follows declared capabilities.
3. Upload a CSV containing valid, invalid, unknown, and personal-data columns.
4. Map columns and run dry-run.
5. Verify no rows were written and download the rejected-row report.
6. Commit the unchanged valid dry-run and verify exact row counts and audit history.
7. Preview a pending migration, confirm its snapshot, apply, and roll back where supported.

## End-to-End Scenario D — Production and Recovery

1. Introduce a blocking readiness failure and attempt Production.
2. Verify the transition is blocked.
3. Resolve checks or use the designed typed override and switch.
4. Verify activity and warnings.
5. Enter Maintenance and verify anonymous/admin behavior.
6. Enable custom login and rate limiting; trigger a lockout.
7. Set `COREX_LOGIN_UNGUARD` and verify default recovery.
8. Run `wp --path=wp corex security reset-login` and verify idempotent recovery and audit.

## End-to-End Scenario E — Access Request and Grant

1. Use a low-privilege user to request one CoreX area.
2. Review and approve from an administrator account.
3. Verify ability, notification capture, and audit.
4. Attempt to remove the current administrator's critical access and verify rejection.
5. Attempt to remove the last full-access administrator and verify rejection.
6. Activate a supported external role manager and verify native capabilities become read-only while CoreX abilities remain manageable.

## End-to-End Scenario F — Blog and Email

1. Move a native post through review, changes, approval, schedule, and publish.
2. Add review notes, assignment, and due date.
3. Record real view/read/share events and verify 7/30/90-day aggregates and top posts.
4. Moderate a native comment through approve/reply/edit/spam/trash.
5. Create/edit an Email Studio template and layout, preview desktop/mobile/RTL, validate variables, capture a test, and inspect/resend its attempt.

## End-to-End Scenario G — Setup and Rollback

1. Complete and skip selected wizard steps, then resume them.
2. Configure brand, select a kit and demo level, and open live preview.
3. Create keep/replace/suffixed-slug conflict choices.
4. Verify apply is blocked until backup succeeds.
5. Apply and inspect created/adopted/skipped/conflicted results.
6. Roll back and verify unrelated owner content remains.

## Full Automated Gates

```powershell
composer validate --strict
composer test
composer test:integration
npm.cmd run lint:js
npm.cmd run lint:css
npm.cmd run test:js -- --runInBand
npm.cmd run build
npm.cmd run verify:dependencies
npm.cmd run build:dist
npm.cmd run verify:dist
git diff --check
```

Expected: all commands exit `0`; no product-critical warning is ignored.

## Browser and Visual Gates

Run the existing admin renderer plus feature-specific Playwright suites. For every CoreX admin route and approved front-end/docs surface, verify dark/light, LTR/RTL, 375px/desktop, hover, focus, keyboard, reduced motion, 200% zoom, and applicable loading/empty/error/permission/dependency states.

```powershell
node tests\e2e\render-admin.mjs
npm.cmd run test:e2e
```

Inspect screenshots; automated file generation without visual review is not acceptance.

## Guard Gate

Run every applicable installed guard on the final diff:

- `clean-code-guard`
- `wp-guard`
- `woo-guard` for WooCommerce code
- `test-guard`
- `docs-guard`

Record findings and fixes in task evidence. A fallback command suite does not replace a named guard when its skill is available.

## Final Proof

1. Map every `FR-001`–`FR-167` and `SC-001`–`SC-020` to direct evidence.
2. Search current product code and rendered copy for prohibited placeholder language.
3. Inventory every enabled control and verify its action.
4. Reconcile real metrics with their stores/providers.
5. Confirm no required task remains unchecked.
6. Update `PROGRESS.md`, `ROADMAP.md`, `DECISIONS.md`, design inventory, relevant READMEs, docs, and final report.
