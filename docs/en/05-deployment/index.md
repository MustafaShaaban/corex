---
title: Deployment
description: Run Corex with Docker, and deploy it to Azure, AWS, or shared hosting — full step-by-step recipes.
audience: ops
stability: stable        # recipes authored (phases D3–D6)
last_verified: null
---

# Deployment

Complete, step-by-step recipes for running and shipping Corex. Every recipe deploys a **release tag** (per
[`COREX-FRAMEWORK.md §19`](../../../COREX-FRAMEWORK.md)) and covers secrets, backups, rollback, zero-downtime,
and CI/CD — with a Mermaid topology diagram.

> **Status:** ✅ authored (phases D3–D6).

## Verification status (D12)

All recipes are **authored and statically validated** — the `docker-compose.yml` is valid YAML, `entrypoint.sh`
passes `bash -n`, every command is language-tagged with expected output, every page has a Mermaid topology
diagram, and all internal links resolve. **Live execution is environment-gated**: it needs a Docker daemon and
real Azure/AWS/cPanel accounts this authoring environment lacks. Each page's `last_verified` stays `null` until
it is run on its target — run it and stamp the date.

## Spec 055 readiness profiles

Run the profile check through the readiness command:

```bash
wp corex readiness
```

The deployment row is `environment-gated` until the target environments below are verified on real infrastructure.
Each profile records its package shape, build commands, dependencies, secrets, and blockers.

| Profile | Package shape | Build commands | Dependencies | Secrets | Current blocker |
|---|---|---|---|---|---|
| `minimal` | Corex source, production vendor, and built assets for WordPress | `composer install --no-dev --optimize-autoloader`; `npm ci`; `npm run build` | PHP 8.3+, MySQL/MariaDB, WordPress 7.0+ | DB credentials, WP salts | None known |
| `standard` | Tagged Corex framework release with active core plugins and theme | `composer validate --no-check-publish`; `composer test`; `npm run build` | PHP 8.3+, Node 20+, WP-CLI, WordPress 7.0+ | DB, mail, WP salts | None known |
| `full` | First-party Corex runtime with optional add-ons gated by Corex state | `composer test`; `npm run build`; `npm run test:js` | PHP 8.3+, Node 20+, WP-CLI, add-on files | DB, mail, captcha, WP salts | None known |
| `woo` | Corex plus WooCommerce and Woo kit when the dependency exists | `composer test`; `npm run build`; `wp plugin is-installed woocommerce` | WooCommerce, PHP 8.3+, WordPress 7.0+ | DB, payment keys, WP salts | None known |
| `client-site` | Generated client plugin/theme consuming a Corex release | `wp corex make:site Acme --path=dist/acme`; `wp corex compliance:check` | Corex release package, PHP 8.3+, WordPress 7.0+ | Client DB, mail, WP salts | None known |
| `shared-host` | Flat WordPress tree with Corex copied into `wp-content` | `composer install --no-dev --optimize-autoloader`; `npm run build`; assemble `dist/` | PHP selector, MySQL panel, SFTP/FTP | DB, SFTP, WP salts | Verify PHP extensions, permissions, and no-symlink upload shape |
| `azure-container` | Production Docker image on Azure App Service for Containers | `docker build --target prod -t corex:prod .`; `az webapp config container set` | Docker, Azure CLI, ACR, Azure MySQL | Azure, registry, DB, Key Vault | Requires live Azure subscription and repo secret verification |
| `local-docker` | Docker Compose dev stack with the monorepo mounted into WordPress | `docker compose up -d --build`; `docker compose exec php composer test` | Docker daemon, Docker Compose, bind mounts | Local DB password, WP salts | Requires Docker daemon availability |
| `wp-env-stable` | wp-env stable WordPress target for smoke/browser checks | `npm run env:start`; `npm run test:e2e`; `npm run env:stop` | Docker daemon, `@wordpress/env`, stable WP image | Local wp-env credentials, WP salts | Requires Docker/wp-env availability |
| `wp-env-trunk` | wp-env trunk compatibility target | `npm run env:start -- --update`; `npm run test:e2e`; `npm run env:stop` | Docker daemon, `@wordpress/env`, trunk WP image | Local wp-env credentials, WP salts | Requires Docker/wp-env and may fail on upstream trunk regressions |

## Pages

| Page | Target | Phase |
|---|---|---|
| [`docker.md`](./docker.md) | Docker (dev compose + multi-stage prod image) | ✅ D3 |
| [`azure-app-service.md`](./azure-app-service.md) | Azure App Service | ✅ D4 |
| [`azure-vm.md`](./azure-vm.md) | Azure VM (Ubuntu + nginx + php-fpm + MariaDB + Certbot + UFW) | ✅ D4 |
| [`aws-beanstalk.md`](./aws-beanstalk.md) | AWS Elastic Beanstalk (+ RDS, S3, CloudFront) | ✅ D5 |
| [`aws-ec2-rds.md`](./aws-ec2-rds.md) | AWS EC2 + RDS (manual) | ✅ D5 |
| [`cpanel-shared-hosting.md`](./cpanel-shared-hosting.md) | cPanel shared hosting (no-symlink strategy) | ✅ D6 |
| [`ci-cd.md`](./ci-cd.md) | CI/CD wiring per target | ✅ D6 |
| [`secrets-backups-zero-downtime.md`](./secrets-backups-zero-downtime.md) | Cross-cutting operations | ✅ D6 |
| [`updates-and-distribution.md`](./updates-and-distribution.md) | Self-update, manifests, and the safe-edit boundary | ✅ spec 034 |
