# Quickstart: Modern settings UX (032)

## 1. Pest — the field types
```bash
vendor/bin/pest tests/Unit/Config/SettingsFormTest.php
```
Expected: media → preview + Select/Remove + value input; select → options with the current value selected;
checkbox → a toggle; input types unchanged; every value escaped. Green.

## 2. Live — the screen renders the modern controls
```bash
wp eval '$c=\Corex\Boot::app()->container(); $f=$c->make("Corex\\Config\\Settings\\SettingsForm"); echo $f->render(fn($k)=>"", "");' --path=wp | grep -E "corex-media|<select|type=.checkbox"
```
Expected: the rendered HTML contains the media control, a select, and any checkbox.

## 3. Browser (env-gated)
Open Corex → Settings: the logo field has a Select-image button (opens the media library, sets a preview); the
captcha driver is a dropdown; the header shows the configured logo.
