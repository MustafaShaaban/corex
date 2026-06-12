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
