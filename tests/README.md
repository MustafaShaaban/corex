# Corex Tests

Per COREX-FRAMEWORK.md §18 and the constitution's Definition of Done, tests are **required**.

| Directory | Level | Tooling |
|---|---|---|
| `Unit/` | Services, repositories (mocks injected via the container) | Pest + Brain Monkey |
| `Integration/` | REST endpoints, abilities, migrations | Pest + wp-phpunit |
| `e2e/` | Subscription, search, contact, payment, RTL flows | Playwright (`@wordpress/e2e-test-utils-playwright`) |

Block-component JS tests (Jest + `@wordpress/jest-preset`) live beside their blocks under
`plugins/corex-blocks/`. The PHP test namespace is `Corex\Tests\` (PSR-4 → `tests/`, wired in
the root `composer.json` `autoload-dev`).

Directories are placeholders until the first module lands its tests.
