# Corex Glossary

Plain-English definitions of the domain terms used across the handbook. The **Arabic** column is filled in the
future translation phase (spec 028, phase 2). Code identifiers are **never** translated — see
[`_translation-memory.md`](./_translation-memory.md).

> This is a living file. Add a row the first time a term needs explaining on a handbook page, and link to it
> from that page.

| Term | Plain-English definition | العربية (Phase 2) |
|---|---|---|
| **Corex** | A professional, Laravel-inspired framework for building WordPress sites — namespace `Corex\`, CLI `wp corex`. | _TODO_ |
| **Service Provider** | A class that registers a module's services into the container and boots them. The single extension seam — every Corex module/add-on registers through one. | _TODO_ |
| **Repository** | The only layer that talks to the data store. Controllers/services ask a repository for data; they never query WordPress directly. | _TODO_ |
| **Container (DI)** | The PSR-11 dependency-injection container that builds objects and supplies their dependencies, so code never calls `new` on a collaborator. | _TODO_ |
| **Service** | A class holding business logic, kept out of controllers and out of the theme. Injected, testable, single-responsibility. | _TODO_ |
| **Controller** | A thin entry point (REST/AJAX/hook) that delegates to services. Holds no business logic and no hand-written security checks. | _TODO_ |
| **Event Bus** | The publish/subscribe seam: code emits an event; listeners react. Decouples "something happened" from "what to do about it". | _TODO_ |
| **Middleware** | Declarative cross-cutting checks (nonce, capability, throttle, sanitize) attached to a route, applied automatically instead of being hand-written. | _TODO_ |
| **Block (dynamic)** | A `corex/*` editor block whose HTML is rendered on the server by a PHP renderer (the editor previews it via `ServerSideRender`). | _TODO_ |
| **Blueprint / Kit** | A manifest that composes existing templates, parts, patterns, and modules into a ready-made site shape (e.g. the Company or Portfolio kit). | _TODO_ |
| **Feature flag** | A named on/off switch (`corex_features_<flag>` option / `FEATURES_<FLAG>` env) that turns a capability on per-site without code changes. | _TODO_ |
| **Guard Gate** | The rule that no diff ships until the relevant guard skill (clean-code / wp / woo / test / docs) has run clean on it. | _TODO_ |
| **Spec Kit** | The spec-first workflow (`/speckit-specify → /plan → /tasks → /implement`) — the spec is written before the code and is the durable artifact. | _TODO_ |
| **AdminGuard** | The shared helper (`Corex\Security\Admin\AdminGuard`) that admin-menu screens route their capability + nonce check through, instead of hand-rolling it. | _TODO_ |
| **Mailer seam** | The neutral `Corex\Mail\Mailer` interface other modules send mail through, so the delivery driver (wp_mail, queue) can change without touching callers. | _TODO_ |
| **Field driver (ACF-optional)** | The abstraction that reads custom fields through ACF when present and native post meta otherwise — so ACF is never a hard dependency. | _TODO_ |
| **Monorepo mapping** | Mapping the repo's `plugins/`, `theme/`, and `addons/` into WordPress's `wp-content/` (via junctions on Windows, symlinks on Linux/macOS, or bind-mounts in Docker) so one source tree runs the site. | _TODO_ |
| **docs-app** | The published documentation **website** (Astro + Starlight) — product/API docs + the generated class reference. Distinct from this in-repo handbook. | _TODO_ |
