# Feature Specification: Corex Brand Identity + Admin Branding (MVP)

**Feature Branch**: `016-branding`

**Created**: 2026-06-10

**Status**: Draft

**Input**: Give Corex its own product identity (a scalable SVG mark in the Corex palette — navy + cyan) and
a configurable logo that replaces the WordPress logo on the login page, the admin footer, and the login
link. Lives in `corex-config`. Kept separate from client/site branding. The branding logic is unit-tested;
the visual admin result needs a browser.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Corex has an identity (Priority: P1) 🎯 MVP

Corex ships a scalable SVG logo (navy `#0B1F3B` + cyan `#00C2FF`, a layered-core mark) used across the admin.

**Independent Test**: The bundled SVG exists, is valid, and uses the Corex palette; the branding service
resolves the logo URL (config override or the bundled default).

### User Story 2 - Corex branding in wp-admin (Priority: P1)

The Corex logo replaces the WordPress logo on the **login page**, the login link points to the site/home,
and the **admin footer** reads "Powered by Corex" — all overridable.

**Independent Test**: The login CSS the service produces references the configured logo; the footer text and
login URL are the configured values. The hooks are registered on boot. (The rendered admin appearance is a
browser check.)

### Edge Cases

- A configured `brand.logo_url` overrides the bundled default everywhere.
- This is the **Corex product** brand (admin/login), never a client site's look (those stay neutral).

## Requirements *(mandatory)*

- **FR-001**: Corex MUST ship a scalable SVG logo in its palette (navy + cyan).
- **FR-002**: A branding service MUST resolve the logo URL (config `brand.logo_url` override → bundled
  default) and produce the login-page logo CSS, the admin footer text, and the login link target.
- **FR-003**: Corex MUST replace the WordPress logo on the login page + admin footer, configurably, via WP
  hooks — without altering any client/site theme branding.
- **FR-004**: The branding lives in `corex-config` and adds no client-facing styling.

## Success Criteria *(mandatory)*

- **SC-001**: The bundled SVG is valid and uses the Corex palette.
- **SC-002**: The branding service resolves the logo URL, login CSS, footer text, and login URL correctly,
  honoring a config override (headless).
- **SC-003**: The admin-branding hooks are registered on boot.
- **SC-004**: The branding logic is fully unit-tested; the rendered admin appearance is verified in a browser.

## Assumptions

- **Packaging.** `corex-config` (`Corex\Config`). The SVG is bundled in the plugin's assets.
- **MVP scope.** Login logo + admin footer + login link. The admin-bar logo node, a Corex admin color
  scheme, and the full settings/dashboard UI (spec 017) are deferred. This is the **product** brand
  (#12A), separate from the neutral client base (#12B).
