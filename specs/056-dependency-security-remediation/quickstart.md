# Quickstart: Verify Dependency Security

## Prerequisites

- Node.js 20 or newer and npm installed.
- PHP 8.3 or newer and Composer installed.
- Dependencies installed from the committed lockfiles.
- Network access to npm and Packagist advisory services.

## Environment gate

Before implementation or release verification, prove the mapped WordPress installation recognizes Corex:

```powershell
wp --path=wp theme list --status=active
wp --path=wp plugin list --status=active
wp --path=wp corex readiness 0.26.1
```

Expected: `corex`, `corex-core`, `corex-blocks`, `corex-config`, and `corex-forms` are recognized; the readiness command completes without a PHP fatal.

## Dependency-security gate

```powershell
npm.cmd run verify:dependencies
```

Expected: all three audit surfaces report `PASS`. Raw advisories may remain only when the policy contains a current, bounded, non-runtime/non-CI exception. A registry timeout reports `UNAVAILABLE` and exits 2.

## Regression suite

```powershell
npm.cmd run test:js
npm.cmd run build
composer validate --no-check-publish
composer test
Push-Location docs-app
npm.cmd run build
Pop-Location
```

Expected: all commands exit 0. Browser/wp-env checks remain environment-gated when Docker is unavailable and must be recorded as such.
