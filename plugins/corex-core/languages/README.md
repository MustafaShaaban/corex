# Translations

This directory holds the catalogues of the shared `corex` text domain. All CoreX plugins, add-ons and the CoreX
theme use this one domain; corex-core loads it on `init` (`Corex\I18n\Catalogue`).

- `corex.pot`: the template, generated with `composer i18n:pot` (WP-CLI). CI generates it on every pull request and
  attaches it to every release; it is not committed.
- `corex-ar.po`: the Arabic catalogue, the source of every Arabic string (spec 121). Its terms and style rules are
  in `glossary-ar.md`.
- `corex-ar.mo`: compiled from it with `node scripts/i18n-arabic.mjs mo`. Commit both together; CI fails when they
  disagree.
- `corex-ar-<md5>.json`: the script translations, written from `corex-ar.po` as the last step of `npm run build`.
  Not committed. `Corex\I18n\ScriptTranslations` serves them when WordPress's languages folder has none.
- `runtime-strings.php`: strings CoreX translates from a value it holds (managed table names), listed so the
  template has them. Never loaded.

A site's own language pack in `wp-content/languages/plugins/` wins over the files here.

## Adding or changing a string

1. Generate the template: `composer i18n:pot`.
2. List what has no Arabic: `node scripts/i18n-arabic.mjs check plugins/corex-core/languages/corex.pot`.
3. Translate those entries in `corex-ar.po` (keep placeholders, markup and `{slots}` exactly; follow
   `glossary-ar.md`), then `node scripts/i18n-arabic.mjs mo`.
4. The check refuses a broken translation; the Arabic proof of the quality checks
   (`tests/e2e/quality/arabic-proof.spec.js`) names any string still English on a screen.

Every release that adds strings updates the catalogue before its tag.
