---
title: Azure Pipelines deployment
audience: deployment
stability: stable
---

# Azure Pipelines deployment

CoreX splits CI responsibilities:

- **GitHub Actions** — PR / code-quality gates (lint, tests, CodeQL, dependency advisories). Unchanged.
- **Azure Pipelines** (`azure-pipelines.yml`) — build the deployable `dist/` artifact and deploy it to hosting.

> **Mode:** this is [Deployment Mode](../04-team-workflow/agent-roles.md#3-deployment-mode). No credentials live in
> the repo — only Azure secret variables and placeholders.

## What the pipeline does

1. Triggers on release tags (`v*`); also runnable manually from the Azure DevOps UI.
2. **Build stage:** Node 20 + PHP 8.3, `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`,
   then `npm run build:dist` (optionally `--client=<slug>`), then `npm run verify:dist`. Publishes `dist/` as the
   `corex-dist` pipeline artifact.
3. **Deploy stage:** runs only when the `deploy` parameter is `true`, gated by the `corex-production` environment
   (add a **manual approval** check to it in Azure DevOps). Uploads the artifact over SFTP, **excluding** production
   runtime files.

## Configure (one-time, in Azure DevOps)

Set these as **secret** pipeline variables (or a service connection) — never commit them:

| Variable | Meaning |
|---|---|
| `SFTP_HOST` | Hostname of the shared host. |
| `SFTP_USER` | SFTP username. |
| `SFTP_PASSWORD` *or* `SFTP_PRIVATE_KEY` | Credential (prefer a key). |
| `SFTP_REMOTE_PATH` | Absolute remote web root to mirror into. |

Add a **manual approval** check to the `corex-production` environment so a human approves every deploy.

## Runtime-file protection (SFTP excludes)

The deploy mirror must exclude these so a deploy never clobbers target state:

```text
wp-config.php
.htaccess
wp-content/uploads/
wp-content/cache/
wp-content/upgrade/
wp-content/debug.log
```

## The placeholder deploy step

`azure-pipelines.yml` ships a **safe placeholder** SFTP step (commented `lftp mirror` example with the excludes
above). Replace it with your org's approved SFTP task / service connection. Until then the build + artifact publish
are fully functional; the deploy stage is opt-in (`deploy: true`) and approval-gated, so nothing deploys by accident.

## Database & uploads

The pipeline deploys **files only**. Database export/import and `wp-content/uploads/` sync are separate steps — see
the [Shared-host dist](./shared-host-dist.md) first-deploy / update / rollback checklists.

## Why `dist/` is generated and never committed

`dist/` is a build artifact assembled from repo source (de-symlinked, dev files stripped). Committing it would
duplicate source, leak build state, and drift. It stays git-ignored; the pipeline rebuilds it every run, and the
server receives only its contents.
