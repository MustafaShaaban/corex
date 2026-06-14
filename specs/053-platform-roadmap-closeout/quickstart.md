# Quickstart / Validation: Platform Roadmap Closeout (053)

Runnable scenarios that prove each user story. Prereqs: the monorepo mapped into a working WP 7.0+/PHP 8.3
install (junction/symlink, `wp/` subdirectory), `composer install`, `npm install`. Browser steps run under the
spec-052 wp-env + Playwright path; where Apache/browser is unavailable, record the step as env-gated.

## US1 — Documentation honesty

```bash
# No stale/false claims remain in hand-authored docs
grep -rniE "bootstrap stage|no framework code yet" README.md docs-app/src/content/docs PROGRESS.md || echo "clean"
```
- **Expect**: no matches in `README.md`; README names the real modules (corex-core/-blocks/-config/-forms +
  add-ons) and accurate setup steps.
- **Expect**: `specs/045/tasks.md` and `specs/049/tasks.md` checkboxes match the code (the Data UI + the
  `--starter`/`--minimal` flag tasks reflect reality).
- **Expect**: `COREX-WORKING-GUIDE.md` (+ constitution DoD) state the feature-PR docs rule with the
  surface↔change mapping.
- **Gate**: `docs-guard` clean on the changed docs.

## US2 — Data screen

```bash
npm run build            # compile the rebuilt corex-config Data app
npm run test:js          # Jest: search/sort/paginate/export/detail + states
```
Then in **Corex → Data** (browser / env-gated):
1. Type in search → list narrows, count/pagination update.
2. Pick a form filter → only that form's rows.
3. Click a column header → reorders; click again → direction toggles (`aria-sort` flips).
4. Page through results.
5. Click **Export** → a CSV of the *current filtered view* downloads (only displayed columns, no secret).
6. Open a row → drawer shows label→value fields + form + date; empty values show `—`.
7. Observe distinct loading, error (disconnect the API), and empty ("No matches") states.
- **Expect**: zero console errors; keyboard-operable; RTL correct.

## US3 — Test buttons

```bash
npm run test:js          # Jest: captcha button module (POST + busy + classified message + no secret)
```
Then on the **settings**/**insights** screens (browser / env-gated):
1. Captcha **Test** with keys set → success message; with a key missing → "add the <name> key"; never shows a
   secret.
2. Insights **Check** → distinguishes ok / local-url / missing-optional-key / invalid-key / network-or-quota,
   each actionable.
- **Expect**: busy state while in flight; accessible announcement; console-clean.

## US4 — `make:site --starter`

```bash
# Starter slice emitted + lints clean
wp corex make:site Acme --starter --path=/tmp/acme
find /tmp/acme -name '*.php' -print0 | xargs -0 -n1 php -l        # all "No syntax errors"
test -f /tmp/acme/plugins/acme-site/REMOVE-EXAMPLE.md && echo "removal guide present"

# Minimal / default omit the slice
wp corex make:site Acme2 --minimal --path=/tmp/acme2
test ! -e /tmp/acme2/plugins/acme2-site/src/Controllers/ExampleController.php && echo "minimal omits slice"

# Pest (headless, no WP needed)
composer test -- --filter=SiteScaffolderStarter
```
- **Expect**: `--starter` generates model/repo/service/controller(envelope)/block/option/test + starter theme
  assets, all `php -l` clean, client-namespaced; default/`--minimal` produce only the lean scaffold; re-run
  without `--force` overwrites nothing.

## Whole-feature gates

```bash
composer test            # Pest unit (record count)
npm run test:js          # Jest
# Guard Gate per diff: clean-code-guard (all prod), wp-guard (REST/admin/block), test-guard, docs-guard
```
- **Done** when: README honest; 045/049 checkboxes truthful; Data screen fully usable with three states;
  captcha+insights buttons work secret-safe; `make:site --starter` runnable with a removal guide; touched
  screens console-clean (or env-gated recorded); all guards clean; PROGRESS/DECISIONS updated.
