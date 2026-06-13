# Quickstart: Validating "Kit Apply Never Leaves a Blank Front Page"

## Prerequisites

- Corex monorepo mapped into `wp/wp-content/`; `composer install` done; WP-CLI resolves against `wp/`.
- The Company kit add-on present (hosts the shared `Corex\Kit` apply path).

## 1. Headless unit proof (the classifier + reset disposition)

```bash
composer test -- --filter=KitPagePlanner
composer test -- --filter=KitPageContent
composer test -- --filter=BlueprintActivatorApply
composer test -- --filter=ResetDisposition
```

**Expected**: green. Key cases — a declared `home` whose slug exists but is **empty** classifies as **adopt**
(not skip); a `home` with real user content classifies as **skip**; after apply the front page points at the
created/adopted home; a soft reset **deletes** a created page but only **empties** an adopted one.

## 2. Reproduce the live bug, then confirm the fix

The current live state already has the bug (front page = blank page 2511). After implementing:

```bash
cd C:/wamp64/www/corex/wp
# before: front page is blank
wp eval 'echo "front=".get_option("page_on_front")." blocks=".substr_count((string)get_post_field("post_content",(int)get_option("page_on_front")),"wp:corex")."\n";'
# apply the Company kit (through the wizard apply path / spec-042 prompt, or a CLI apply harness)
# after: the front page renders the kit home
wp eval 'echo "front=".get_option("page_on_front")." blocks=".substr_count((string)get_post_field("post_content",(int)get_option("page_on_front")),"wp:corex")."\n";'
```

**Expected**: after apply, `blocks` > 0 — the front page now composes the kit's hero/features/cta (SC-001).
Re-running apply does not change `blocks` and creates no duplicate pages (SC-002/SC-004).

## 3. Reset safety

```bash
cd C:/wamp64/www/corex/wp && wp corex reset --dry-run
```

**Expected**: the dry run lists kit **created** pages for deletion and kit **adopted** pages for emptying (not
deletion). A real soft reset retains adopted pages (emptied) and deletes created pages (SC-004).

## 4. Definition of Done

```bash
composer test    # full Pest suite green (existing + new)
```

- `clean-code-guard` + `wp-guard` clean on the diff; `test-guard` on the new tests; `docs-guard` on README/docs.
- `PROGRESS.md` + `DECISIONS.md` updated; CHANGELOG entry on release.

## Success mapping

| Spec criterion | Proven by |
|---|---|
| SC-001 (no blank front page) | §1 adopt+front-page tests; §2 live blocks>0 after apply |
| SC-002 (idempotent, no overwrite) | §1 idempotence test; §2 re-apply blocks unchanged |
| SC-003 (user content preserved) | §1 skip-user-content test |
| SC-004 (reset created vs adopted) | §1 ResetDisposition test; §3 dry-run + reset |
| SC-005 (headless classifier coverage) | §1 all green without WordPress |
| SC-006 (no new dep, no pattern change) | diff review |
