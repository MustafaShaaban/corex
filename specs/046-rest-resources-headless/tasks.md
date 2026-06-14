# Tasks: REST resources & headless

**Feature**: 046-rest-resources-headless · **Branch**: `feature/046-rest-resources-headless`
**Input**: [spec.md](./spec.md) · [plan.md](./plan.md)

**Tests**: REQUIRED (Pest; live route/headless flows env-gated).

**Story legend**: US1 = make:api-resource (P1, MVP) · US2 = routes:list (P1) · US3 = api:docs (P2) ·
US4 = headless mode (P2).

---

## Phase 3: US1 — make:api-resource (P1, MVP)

- [x] T001 [US1] Author the 5 stubs under `packages/cli/stubs/api-resource/` — `controller.stub` (thin: route→validate→service→resource→`ResponseEnvelope`), `routes.stub` (`register_rest_route` under the app REST namespace, declares spec-005 middleware + a permission callback), `request.stub` (validation shape), `resource.stub` (output DTO — declared fields only), `test.stub` (a green scaffold test).
- [x] T002 [P] [US1] Pest `tests/Unit/Cli/ApiResourceScaffolderTest.php` — renders all files, writes under the app paths/namespace/prefix, idempotent (no overwrite without `--force`), and the generated controller passes `php -l`.
- [x] T003 [US1] Implement `Corex\Cli\Generators\ApiResourceScaffolder` (+ `ApiResourceScaffoldResult`) — pure render-all-before-write (modelled on `BlockScaffolder`) until T002 green.
- [x] T004 [US1] Wire `make:api-resource` into `MakeCommand` + `CliServiceProvider` (WP-CLI-gated); verify `wp corex make:api-resource Project` live.

## Phase 4: US2 — routes:list (P1)

- [x] T005 [P] [US2] Pest `tests/Unit/Cli/RouteListTest.php` — `RouteDescriptor` (method/path/permission) + `RouteList` formats readable grouped lines.
- [x] T006 [US2] Implement pure `Corex\Cli\Routes\{RouteDescriptor,RouteList}` until T005 green.
- [x] T007 [US2] `RoutesCommand` (`routes:list`) — reads `rest_get_server()->get_routes()` filtered to the Corex/app namespaces → descriptors → `RouteList`; WP-CLI-gated. Verify live.

## Phase 5: US3 — api:docs (P2)

- [x] T008 [P] [US3] Pest `tests/Unit/Cli/ApiDocsGeneratorTest.php` — descriptors + the envelope schema → a valid OpenAPI 3 structure (paths/methods/auth), no secret.
- [x] T009 [US3] Implement pure `Corex\Cli\Docs\ApiDocsGenerator` + the `api:docs` command (WP-CLI-gated) until T008 green.

## Phase 6: US4 — headless mode (P2)

- [x] T010 [US4] Confirm/expose the headless read surface (content/CPTs/forms/options/menus) as cap-gated, envelope-shaped routes; ensure nonce + application-password auth work; a protected resource refuses unauth.
- [x] T011 [US4] Document the headless surface + auth (nonce / application password; JWT/OAuth out of scope).

## Phase 7: Polish

- [x] T012 [P] Docs: docs-app `guides/rest.md` (make:api-resource, routes:list, api:docs) + `guides/headless.md`; CLI README.
- [x] T013 Guard Gate (clean-code, wp-guard — generated route/permission/no-secret/escaping, test-guard, docs-guard).
- [x] T014 Suites green (`composer test`); record counts. Live CLI/headless env-gated.
- [x] T015 Update `PROGRESS.md` + `DECISIONS.md` #80; NEXT STEP. Commit → PR → CI → merge.

---

## Dependencies & order

- **MVP = US1** (the generator). US2 (discovery) feeds US3 (docs). US4 (headless) is mostly exposing + documenting.
- TDD: T002→T003, T005→T006, T008→T009.
- **Parallel**: T002/T005/T008 (`[P]`), docs T012.
