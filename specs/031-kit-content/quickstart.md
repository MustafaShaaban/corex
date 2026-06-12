# Quickstart: Kits that build a real site (031)

## 1. Pest — the planner
```bash
vendor/bin/pest tests/Unit/Kit/KitPagePlannerTest.php
```
Expected: `toCreate` returns only pages whose slug isn't already present; empty when all exist. Green.

## 2. Live — apply a kit, see pages
```bash
wp eval '$c=\Corex\Boot::app()->container(); $a=$c->make("Corex\Kit\BlueprintActivator"); $a->seedPages($c->make("Corex\Kit\Company\CompanyBlueprint")->pages());' --path=wp
wp post list --post_type=page --path=wp   # the kit pages exist; front page set
```
Expected: home/about/contact pages created (published), front page set, `corex_kit_seeded_pages` populated.

## 3. Idempotent + reset
- Run step 2 again → no duplicates (planner skips existing slugs).
- `wp corex reset --path=wp` → the kit-seeded pages are trashed; non-kit content stays.

## 4. Browser (env-gated)
Visit the site: the kit's front page renders its composed patterns.
