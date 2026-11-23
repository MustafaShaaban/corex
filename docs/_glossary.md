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
| **Block (dynamic)** | A `corex/*` editor block whose HTML is rendered on the server by a PHP renderer (a static block is edited in place, drawn with the page's elements; a block that shows records previews via `ServerSideRender`; DECISIONS #656). | _TODO_ |
| **Blueprint / Kit** | A manifest that composes existing templates, parts, patterns, and modules into a ready-made site shape (e.g. the Company or Portfolio kit). | _TODO_ |
| **Feature flag** | A named on/off switch (`corex_features_<flag>` option / `FEATURES_<FLAG>` env) that turns a capability on per-site without code changes. | _TODO_ |
| **Guard Gate** | The rule that no diff ships until the relevant guard skill (clean-code / wp / woo / test / docs) has run clean on it. | _TODO_ |
| **Spec Kit** | The command chain (`/speckit-specify → /plan → /tasks → /implement`) CoreX used to write specs until constitution v2.0.0 retired it. Older specs keep its `plan.md` and `tasks.md`. | _TODO_ |
| **CoreX loop** | How every change is built since constitution v2.0.0: grill the idea, write the spec with its slices, build one slice test first, run the guards and a two-model review, open the PR. | _TODO_ |
| **Slice** | One part of a spec that ships as one pull request and works on its own. A spec lists its slices and which ones each waits on. | _TODO_ |
| **AdminGuard** | The shared helper (`Corex\Security\Admin\AdminGuard`) that admin-menu screens route their capability + nonce check through, instead of hand-rolling it. | _TODO_ |
| **Mailer seam** | The neutral `Corex\Mail\Mailer` interface other modules send mail through, so the delivery driver (wp_mail, queue) can change without touching callers. | _TODO_ |
| **Field driver (ACF-optional)** | The abstraction that reads custom fields through ACF when present and native post meta otherwise — so ACF is never a hard dependency. | _TODO_ |
| **Monorepo mapping** | Mapping the repo's `plugins/`, `theme/`, and `addons/` into WordPress's `wp-content/` (via junctions on Windows, symlinks on Linux/macOS, or bind-mounts in Docker) so one source tree runs the site. | _TODO_ |
| **docs-app** | The published documentation **website** (Astro + Starlight) — product/API docs + the generated class reference. Distinct from this in-repo handbook. | _TODO_ |
| **Visitor kit** | The design of CoreX's public side in four layers: foundations, components, blocks and templates. It lives in the CoreX design system; `design/handoffs/visitor-kit.md` records its approval. | _TODO_ |
| **Block (kit)** | In the visitor kit, a page section built only from components: a hero, a pricing table, a footer. Not the same word as a WordPress block, which is one unit in the editor; a kit block usually ships as a pattern of several. | _TODO_ |
| **Section style** | A named look of the WordPress Group block that a section takes: quiet band, dark band or brass band. | _TODO_ |
| **Dark band** | A section that is dark in both the light and the dark theme. It hands its own colours to everything inside it, so a component needs no second set of rules. | _TODO_ |
| **Brass band** | A section with the brass accent behind it, graphite text and a graphite button. One on a page at most. | _TODO_ |
| **Editor parity** | A static block is drawn in the editor with the same elements as on the page and its text is typed where it stands (DECISIONS #656, #671). | _TODO_ |
| **Block kind** | How a CoreX block is edited, declared in its `block.json` as `corex.editor`: `in-place` (drawn by the editor, typed on the canvas), `records` (shows records it does not hold, so it keeps a server render), `none`, or `pending` (its turn has not come). | _TODO_ |
| **Parity fixture** | The `parity.json` beside an in-place block's `block.json`: the class prefix, attributes with every field filled, inner blocks where it holds blocks, and the tree of elements both the editor and the renderer must draw. | _TODO_ |
| **Child block** | A block that exists only inside its parent and holds any blocks: `corex/tab`, `corex/accordion-item`, `corex/slide`. | _TODO_ |
| **Navigation breakpoint** | The one window width, `settings.custom.nav.breakpoint` in `theme.json` (782px in the CoreX theme), at which a CoreX header changes between its phone form (the menu behind its button) and its desktop form. A child theme moves it by setting its own value; CoreX writes that value into the header's width rules (spec 137, DECISIONS #692). | _TODO_ |
| **Chosen records** | A Query Loop variation that lists hand-picked records of one post type in the order picked. The ids are held in the loop's query (`corexChosen`). | _TODO_ |
| **Item source** | What a carousel's items are: its `corex/slide` blocks, one Query Loop, or one Gallery. A carousel holds exactly one. | _TODO_ |
| **Ticker** | A mode of the carousel that moves its items in one continuous line, with a Pause button always shown. The copy that makes the loop seamless is decoration, hidden from screen readers. | _TODO_ |
| **Reveal** | A block setting (none, fade, fade and rise) that animates a block in as it comes into view. Only the script hides a block; with no script, reduced motion, print or the editor's canvas it is shown at once. | _TODO_ |
| **Count-up** | An option of `corex/stat`, and the attribute `data-corex-count-up`, that counts a figure from zero to its value once when it comes into view. The page holds the final value as text. | _TODO_ |
| **Filter state** | The `filter-<id>-*` values in the address that belong to one Query Loop, carrying term ids, so a shared link shows the same list. | _TODO_ |
