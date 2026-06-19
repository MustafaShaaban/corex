# Contract: Component Coverage and Free/Core Boundaries

## Purpose

Classify the minimum UI/content capabilities needed for two company-identity websites without triggering a full
Corex visual redesign or unnecessary custom blocks.

## Component Coverage Item

```json
{
  "need": "contact form",
  "mechanism": "form-field",
  "source": "plugins/corex-forms",
  "accessibility": "labels, errors, keyboard, WCAG 2.2 AA",
  "token_strategy": "theme.json CSS variables",
  "rtl_strategy": "logical properties",
  "free_pro": "free-core"
}
```

## Mechanisms

- `corex-block`
- `wordpress-core-block-style`
- `pattern`
- `form-field`
- `admin-component`
- `utility`
- `missing`
- `deferred`
- `pro-candidate`

## Boundary Rules

- Native WordPress/core block mechanisms win before custom blocks.
- Security, accessibility, RTL, i18n, basic forms, basic captcha/honeypot, basic media fields, basic make:site, and
  basic deployment docs remain Free/Core.
- Advanced automation, vertical workflows, white-label admin, advanced data/email/media, and governance dashboards
  may be Pro candidates.
- `missing` items require task approval before implementation.
- No item may introduce client-specific branding into Corex framework folders.
