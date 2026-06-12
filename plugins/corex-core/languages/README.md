# Translations

This directory holds the Corex translation template and catalogs for the shared `corex` text domain.

- `corex.pot` — the template, regenerated with `composer i18n:pot` (requires WP-CLI).
- `corex-{locale}.po` / `.mo` — per-locale catalogs you add (e.g. `corex-ar.po`).

All Corex plugins and add-ons share this one domain, loaded on `init` by corex-core.
