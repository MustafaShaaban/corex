# Implementation Plan: A CoreX site says it runs CoreX

**Spec**: [spec.md](spec.md) · **Branch**: `spec/111-generator-tag`

## Constitution Check

| Principle | How this change satisfies it |
|---|---|
| I theme is a skin | The mark is printed by `corex-core`. The theme gains nothing. |
| II plugins boot themselves | Hooked from `ThemeServiceProvider::boot()`, a core provider, on `wp_head`. Any theme that prints the standard head gets it. |
| III thin controllers / fat services | No controller, no service, no data. One small class prints one line on one hook. |
| IV everything injected | The class is bound in the container and resolved by the provider. |
| V runtime tokens | No styling. |
| VI conditional assets | No asset. |
| VII declarative security | No route. The one value printed is escaped with `esc_attr()` (FR-008). |
| VIII RTL-first | No visible output. |
| IX no optional dep is hard | Uses WordPress's own `wp_head` only. |
| X spec is source of truth | Spec 111 is written and its one question answered before this plan. |

## The design

**`Corex\Theme\GeneratorTag`** (`plugins/corex-core/src/Theme/GeneratorTag.php`):

- `NAME = 'CoreX'`, `FILTER = 'corex_generator_tag'`.
- `markup(): string` returns `<meta name="generator" content="CoreX" />` followed by a newline, or
  `''` when `apply_filters(FILTER, true)` is false. Read at print time (FR-005).
- `print(): void` echoes `markup()`. It is the `wp_head` callback.

The markup copies WordPress's own (`get_the_generator('xhtml')`) so the two lines read alike.

**Wiring**: `ThemeServiceProvider` binds the class as a singleton and in `boot()` adds
`add_action('wp_head', [$tag, 'print'])` at the default priority, so it prints right after
WordPress's tag (registered first, same priority). Its docblock grows from "brand overrides" to
"what the framework puts into the front end's theme layer". A separate provider would mean a new
entry in `Boot::CORE_PROVIDERS` and the add-on resolver for one hook.

**Independent of WordPress's tag (FR-004)**: its own `wp_head` callback, not a filter on
`the_generator` / `get_the_generator_*` as WooCommerce does. A site that removes `wp_generator`
keeps CoreX's.

**Coming soon (FR-006)**: `ComingSoonHead::quiet()` already runs only for a request being served
the page. It adds `add_filter(GeneratorTag::FILTER, '__return_false', PHP_INT_MAX)`, so the page
uses the public filter like any other site code, and a site callback that answers true at a normal
priority cannot bring the mark back onto the page. The real site served through the mode never
calls `quiet()` and keeps the mark.

**Not printed elsewhere (FR-007)**: `wp_head` does not run on admin screens (`admin_head`), the
login page (`login_head`), feeds, REST, AJAX or embeds (`embed_head`). Nothing to do; pinned by a
test that `print` is hooked on `wp_head` alone.

## Tests

| What | Where | Proves |
|---|---|---|
| The markup, the filter off, the value escaped, no digits in it | `tests/Unit/Theme/GeneratorTagTest.php` (Pest, Brain Monkey) | FR-001, FR-002, FR-005, FR-008 |
| Real `wp_head` prints the mark once, with WordPress's tag removed too, and with the filter off prints none; `print` is hooked on `wp_head` and no other hook | `tests/Integration/Theme/GeneratorTagTest.php` (real `./wp`) | FR-001, FR-003, FR-004, FR-005, FR-007 |
| The Coming soon page prints no generator tag at all | `tests/Integration/Operations/ComingSoonHeadTest.php` already asserts no `<meta name="generator">`; with CoreX's mark now registered it fails without the `quiet()` change. Kept, and its "ordinary page" test asserts CoreX's mark is there before `quiet()` | FR-006 |
| A signed-out visitor's Coming soon page has no generator tag; an administrator's real site has CoreX's | `tests/e2e/coming-soon.spec.js` line 290 already asserts the first; the administrator test gains the second | FR-006, SC-003 |
| A plain front-end page carries exactly one CoreX mark | `tests/e2e/` front-end check | SC-001 |

## Docs

- `docs/en/03-operations/security.md`: what the site says about itself, the mark, no version and
  why, the one-line filter to turn it off (FR-011).
- `CHANGELOG.md` under Unreleased; `PROGRESS.md`; `DECISIONS.md` #372.
- The detection rule: `contracts/detection-rule.md` (FR-009, FR-010). Submitting it is the owner's.

## Risks

- **Scanners' acceptance**: the open list asks for ten public sites. Stated in the contract; not a
  code risk.
- **Duplicate marks** if a theme calls `wp_head()` twice: same as WordPress's tag; not handled.
