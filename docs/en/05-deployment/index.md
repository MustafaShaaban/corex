---
title: Deployment
description: Run Corex with Docker, and deploy it to Azure, AWS, or shared hosting — full step-by-step recipes.
audience: ops
stability: planned       # authored in phases D3–D6
last_verified: null
---

# Deployment

Complete, step-by-step recipes for running and shipping Corex. Every recipe deploys a **release tag** (per
[`COREX-FRAMEWORK.md §19`](../../../COREX-FRAMEWORK.md)) and covers secrets, backups, rollback, zero-downtime,
and CI/CD — with a Mermaid topology diagram.

> **Status:** scaffolded; authored in **phases D3–D6**.

## Pages (planned)

| Page | Target | Phase |
|---|---|---|
| [`docker.md`](./docker.md) | Docker (dev compose + multi-stage prod image) | ✅ D3 |
| [`azure-app-service.md`](./azure-app-service.md) | Azure App Service | ✅ D4 |
| [`azure-vm.md`](./azure-vm.md) | Azure VM (Ubuntu + nginx + php-fpm + MariaDB + Certbot + UFW) | ✅ D4 |
| [`aws-beanstalk.md`](./aws-beanstalk.md) | AWS Elastic Beanstalk (+ RDS, S3, CloudFront) | ✅ D5 |
| [`aws-ec2-rds.md`](./aws-ec2-rds.md) | AWS EC2 + RDS (manual) | ✅ D5 |
| `cpanel-shared-hosting.md` | cPanel shared hosting (no-symlink strategy) | D6 |
| `ci-cd.md` | CI/CD wiring per target | D6 |
| `secrets-backups-zero-downtime.md` | Cross-cutting operations | D6 |
