# Quickstart: Interactive, inline-editable blocks (029)

## 1. Jest — the editor components (no browser)

```bash
npm run test:js
```

Expected: each changed block's `index.test.js` passes — `registerBlockType(metadata.name)`, `save()===null`, the
`edit` renders `RichText` for its text fields (and the form block renders a `SelectControl` fed by the form
list). Green.

## 2. Pest — renderers + the form-list route

```bash
vendor/bin/pest tests/Unit/Ui/ComponentBlocksTest.php tests/Unit/Forms/FormsListControllerTest.php
```

Expected: rich fields render via `wp_kses_post` (bold/link preserved, scripts stripped); plain fields stay
`esc_*`; the accordion legacy-string fallback still renders; `FormsListController` returns `[{slug,label}]` and
refuses without `edit_posts`. Green.

## 3. Build

```bash
npm run build
```

Expected: all blocks compile (`build/blocks/<name>/…`) with no errors.

## 4. Real-WP route check

```bash
wp eval 'echo (string) rest_url("corex/v1/forms");' --path=wp
# or hit it with a logged-in nonce; expect a JSON array of {slug,label}
```

Expected: the route is registered and returns the registered forms (cap-gated).

## 5. Browser smoke (env-gated)

In a real editor (Apache up): insert a stat/testimonial/pricing/accordion block and **type into it on the
canvas**; insert the form block and **pick a form from the dropdown**. Visual confirmation needs a browser this
headless env lacks.
