# Quickstart: Validating Unified Kit Activation

## Prerequisites

- Spec 041 implemented (create/adopt/skip + `ApplyOutcome`). Corex monorepo mapped into `wp/wp-content/`;
  `composer install`; WP-CLI resolves against `wp/`. The Company kit add-on present.

## 1. Headless unit proof

```bash
composer test -- --filter=ApplyPreview
composer test -- --filter=BlueprintKitProvisioner
composer test -- --filter=KitActivationView
composer test -- --filter=SiteStatusCard
composer test -- --filter=AddonsScreenKitPrompt
```

**Expected**: green. Preview is read-only and its dispositions equal what Apply produces; the prompt/summary
view models list created/populated/skipped pages + front target + links; the card shows the count, applied
kits, and the empty state; enabling a kit yields a pending prompt while a non-kit add-on does not; Apply/Dismiss
require AdminGuard.

## 2. Integration — one shared apply path

```bash
composer test -- --filter=KitActivationIntegration
```

**Expected**: enable→Apply produces the same `ApplyOutcome` shape and the same seeded pages as the Setup Wizard
apply for the same kit (no divergent behavior).

## 3. Live verification (admin, env-gated browser smoke)

```bash
cd C:/wamp64/www/corex/wp
# the provisioner binding resolves and lists applicable kits
wp eval 'echo \Corex\Boot::app()->container()->has(\Corex\Provisioning\KitProvisioner::class) ? "provisioner: bound\n" : "provisioner: UNBOUND\n";'
# dashboard submission count source
wp post list --post_type=corex_submission --format=count
```

**Expected**: provisioner bound; the Corex → Add-ons screen shows an "Apply starter content?" prompt after
enabling a kit; choosing Apply seeds the kit and shows a "what changed" summary; the Corex dashboard "Site
status" card shows the applied kit, the submission count linking to Corex → Data, and the front-page status.
(The visual admin confirmation is the Apache-gated browser smoke.)

## 4. Definition of Done

```bash
composer test    # full suite green (incl. 041)
```

- `clean-code-guard` + `wp-guard` clean; `test-guard` on new tests; `docs-guard` on docs-app/READMEs.
- `PROGRESS.md` + `DECISIONS.md` updated; CHANGELOG on release.

## Success mapping

| Spec criterion | Proven by |
|---|---|
| SC-001 (prompt previews accurately, no pre-apply change) | §1 ApplyPreview read-only + AddonsScreenKitPrompt |
| SC-002 (apply builds front page + summary) | §2 integration; §3 live |
| SC-003 (wizard + prompt one shared path) | §2 integration |
| SC-004 (idempotent re-apply) | §1/§2 (reuses spec 041) |
| SC-005 (dashboard finds submissions + status) | §1 SiteStatusCard; §3 live count |
| SC-006 (gated, no change without cap/nonce) | §1 AddonsScreenKitPrompt gating |
| SC-007 (no new dep / pattern change) | diff review |
