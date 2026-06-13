# Quickstart: Validating Junction/Symlink-Safe Block Asset URLs

How to prove the feature works. Two layers: headless unit proof (the regression that the live env can't
reproduce) and a live verification on the real WordPress install.

## Prerequisites

- The Corex monorepo mapped into `wp/wp-content/` (junctions on this Windows box; symlinks on POSIX).
- `composer install` done; WP-CLI available (`wp` resolves against `C:/wamp64/www/corex/wp`).

## 1. Headless unit proof (the regression — mount-independent)

The malformed-URL failure does **not** reproduce under the current junction mount, so it is proven against
synthetic paths instead of a live repro (see spec Assumptions).

```bash
composer test -- --filter=BlockPathResolver
composer test -- --filter=AssetUrlHealth
composer test -- --filter=BlockAssetsProbe
```

**Expected**: green. Key case — `BlockPathResolverTest` feeds a block dir at the *real* monorepo location
outside the plugins dir (e.g. `C:/wamp64/www/corex/addons/corex-ui/build/blocks/posts`) with a mount map
`{ 'C:/wamp64/www/corex/addons/corex-ui' => 'corex-ui' }` and asserts the result is
`<WP_PLUGIN_DIR>/corex-ui/build/blocks/posts` — i.e. a path that yields an under-`wp-content` URL. The
already-under-plugins input returns unchanged.

## 2. Live verification — asset URLs still clean (no regression)

```bash
cd C:/wamp64/www/corex/wp && wp eval-file ../specs/040-block-asset-urls/_scan.php
```

(or reuse the ad-hoc scan from the deep review). **Expected**: `TOTAL_ASSETS=<n> BAD=0` — every registered
`corex/*` block's editor/view/style URL rooted under `…/wp-content/plugins/<plugin>/…`, byte-for-byte the
same as before the change (SC-002).

## 3. Live verification — the health probe reports

```bash
cd C:/wamp64/www/corex/wp && wp corex doctor
```

**Expected**: a **Block assets** line with a passing status ("All Corex block scripts and styles resolve
correctly."). In **Tools → Site Health** the same check appears under the Corex badge.

To prove the failing path without breaking the install, the `BlockAssetsProbeTest` asserts that a collected
set containing one drive-letter URL yields `Critical` naming that block — the observable behaviour an
operator would see if a future mount produced a bad URL.

## 4. Definition-of-Done checks

```bash
composer test            # full Pest suite green (existing + new)
```

- `clean-code-guard` + `wp-guard` clean on the diff; `test-guard` on the new tests; `docs-guard` on the
  README/docs-app updates.
- `PROGRESS.md` + `DECISIONS.md` updated; CHANGELOG entry on release.

## Success mapping

| Spec criterion | Proven by |
|---|---|
| SC-001 (100% assets 200 across mounts) | §1 resolver maps every mount style to under-plugins; §2 live BAD=0 |
| SC-002 (no regression on junction) | §2 BAD=0, URLs unchanged |
| SC-003 (failures surfaced + named) | §3 doctor/Site Health line; `BlockAssetsProbeTest` Critical-names case |
| SC-004 (headless unit coverage incl. regression) | §1 all green without WordPress |
| SC-005 (no new dep, no build/block.json change) | diff review; composer/package lock unchanged |
