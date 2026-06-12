# Corex Translation Memory — locked English terms

Terms in this file are **never translated** in any language (including the future Arabic phase). They are code
or product identifiers; translating them would break references, commands, or recognition.

> Rule (spec 028, FR-010): code identifiers, inline code blocks, env vars, hook names, CLI flags, and file
> paths stay in English. A translator fills the Arabic prose around them but leaves every term below verbatim.

## Code identifiers (always English)

- **Namespaces & classes**: anything starting `Corex\` — e.g. `Corex\Foundation\ServiceProvider`,
  `Corex\Security\Admin\AdminGuard`, `Corex\Mail\Mailer`, `Corex\Config\Addons\AddonRegistry`.
- **Method / function names**: e.g. `register()`, `boot()`, `render()`, `permits()`, `verifiedPost()`.
- **Hook names**: e.g. `plugins_loaded`, `init`, `admin_menu`, `block_categories_all`, `wp_abilities_api_init`.
- **CLI commands & flags**: `wp corex`, `make:model`, `make:block`, `docs:generate`, `reset`, `--hard`,
  `--yes-i-mean-it`, `--dry-run`, `--force`, `--path=wp`.
- **Env vars & option keys**: `COREX_*`, `FEATURES_*`, `corex_features_<flag>`, `corex_setup_demo_seeded`.
- **File / directory paths**: `wp-content/`, `plugins/corex-core/`, `theme/theme.json`, `docs-app/`, `brand.json`,
  `composer.json`, `package.json`, `.env`.

## Product, tool & service names (always English)

Corex · WordPress · WP-CLI · Composer · npm · Node · PHP · Pest · Jest · Playwright · Spec Kit ·
Astro · Starlight · Mermaid · Docker · docker compose · nginx · php-fpm · MariaDB · MySQL · redis · mailpit ·
WAMP · XAMPP · wp-env · Apache · Ubuntu · Debian · macOS · Windows · Linux ·
Azure · Azure App Service · Azure VM · Azure Pipelines · AWS · Elastic Beanstalk · EC2 · RDS · S3 · CloudFront ·
cPanel · phpMyAdmin · Certbot · UFW · Git · GitHub · GitHub Actions · Conventional Commits.

## Corex feature names kept in English (recognizability)

- `corex/*` block names: `corex/stat`, `corex/testimonial`, `corex/pricing`, `corex/accordion`, `corex/posts`,
  `corex/form`, `corex/projects`, `corex/copyright`, `corex/breadcrumbs`.
- Module/add-on slugs: `corex-core`, `corex-blocks`, `corex-forms`, `corex-config`, `corex-ui`, `corex-email`,
  `corex-careers`, `corex-newsletter`, `corex-bookings`, `corex-captcha`, `corex-kit-company`,
  `corex-kit-portfolio`, `corex-kit-woo`.

## How to use this file

When adding a new page, scan it for any identifier that matches the categories above and ensure it is in a
language-tagged code span/block (so the future translation pass leaves it untouched). Add any new locked term
here the first time it appears.
