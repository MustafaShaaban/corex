# Corex Add-ons

Optional, installable domain features. Each add-on is a **Composer package** that registers
with the service container (COREX-FRAMEWORK.md §14) — loosely coupled, never depending on
another add-on, and installable via `wp corex install corex/<addon>`.

Planned add-ons (each gets its own Spec Kit spec when its turn comes):

- `corex-profile-manager` — frontend accounts; detect-and-defer to WooCommerce My Account.
- `corex-forms` — forms engine (reuses security middleware + event bus).
- `corex-woo` — WooCommerce extensions (HPOS-safe).
- `corex/email` — **Corex Mail (Email Studio)** — templates, event triggers, queue, attachments
  (spec: `COREX-EMAIL-ADDON.md`).

This directory is intentionally empty until the first add-on is built. Do not scaffold an
add-on here without its spec.
