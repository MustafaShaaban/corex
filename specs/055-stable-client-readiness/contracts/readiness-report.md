# Contract: Readiness Report

## Purpose

Summarize client-readiness status across all spec 055 categories with evidence.

## Required Categories

- Add-on runtime gating
- WooCommerce gating
- Metadata/version consistency
- CI/security hardening
- make:site validation
- Deployment readiness
- Component coverage
- Free/Core vs Pro boundaries
- Multi-agent safety

## Finding Shape

```json
{
  "category": "ci-security",
  "status": "warning",
  "summary": "Fast CI is PHP-only; JS/docs/browser checks exist in separate workflows",
  "evidence": [".github/workflows/ci.yml", ".github/workflows/e2e.yml", ".github/workflows/docs.yml"],
  "blocking": false,
  "next_action": "Add or document PR coverage policy"
}
```

## Rules

- Status is one of `pass`, `fail`, `warning`, `environment-gated`, or `not-run`.
- Every required category appears.
- Environment-gated findings name the missing environment and the command/profile that would verify it.
- Blocking findings must name the next action before client-site work proceeds.
- Report output may be Markdown, JSON, CLI table, or docs page as long as the fields are present.
