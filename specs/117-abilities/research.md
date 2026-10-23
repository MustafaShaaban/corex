# Research: Abilities that mean something

Every decision here was taken under the owner's 2026-10-21 mandate, with the planning workspace's recommended
answer.

## R1. One map from screen to ability

**Decision**: `Corex\Access\ScreenAbilities` holds, per CoreX page slug, its section key, its ability and whether
the view ability opens it read-only. `AdminPage::requestAbilityFor()`, the menus, the render checks and the Access
screen's requirements card read it.

**Rationale**: the denied page, the menu and the check disagreed before (spec 083 removed a sentence that named the
wrong capability). One map cannot.

**Alternatives**: a capability per screen registered with WordPress (`corex_open_forms`), mapped by
`map_meta_cap`: a second vocabulary for owners to learn.

## R2. Site administrators keep everything

**Decision**: in `AbilityCompatibility`, anyone holding `manage_options` gets every ability and no role effect is
applied to them. `AccessPolicy::preview()` refuses a Deny on a role that administers the site
(`site_administrator_role`). A stored Deny stays in the table, is not applied, and is listed on the Overview tab.

**Rationale**: ACC-D11; it keeps every client owner's access unchanged when screens move.

**Alternatives**: delete stored Denies on upgrade: the owner never chose that.

## R3. Menus when a person lacks "Access CoreX admin"

**Decision**: the top-level CoreX menu and Overview are registered with the capability the person holds (manage,
then view, else manage), and WordPress points the menu at their first allowed screen. The rail reads the submenu
under its real parent (`$_wp_real_parent_file`).

## R4. The view ability

**Decision**: `corex_view_screens`, "See CoreX screens without changing them", group CoreX admin, risk sensitive,
implied by "Access CoreX admin", requestable. `ScreenAccess::mode()` answers manage, view or none per screen. Read
routes accept manage or view; write routes and form handlers accept manage only.

**Read-only rendering**: `AdminPage::open()` takes a read-only flag: a "Read only" chip in the header, a sentence
with the ability's name, and the request form or the matrix sentence. `assets/read-only.js` marks every input,
select, textarea and button inside the content (except tabs and links) with `aria-disabled`, `readonly` where it
applies and `aria-describedby` to the sentence, and stops clicks, key presses that change a value, and submits. It
observes the content, so the React islands of Operations & Security and Insights are covered. Controls stay
focusable, so their reason is announced.

**Alternatives**: `disabled` on every control: not focusable, so nothing says why.

## R5. Recording refusals of routes and saves

**Decision**: `Corex\Access\AbilityCheck::allows($ability, $section)` returns `current_user_can($ability)` and, when
false for a signed-in person, fires `corex_access_refused` with kind `action` and the section. `AccessAuditLog`
records it, counted per person, section and hour.

## R6. Email Studio and delivery

**Decision**: Email Studio checks `corex_manage_email`. The "Configure delivery" button is replaced, for anyone
who cannot administer the site, by a sentence. Settings fields `mail.provider` and `mail.live_delivery` carry
`site_admin: true`: shown disabled with a sentence, and skipped on save, for anyone else.

## R7. Matrix states

**Decision**: per cell: role administers the site → `allowed_admin`; ability locked → `locked`; stored Allow →
`allowed_set`; stored Deny → `denied_set`; else `not_granted`. A stored Deny on an administering role is flagged
`ignored_deny`. Counts on the overview count `allowed_admin` and `allowed_set`.

## R8. The history

**Decision**: `AccessHistory` reads the activity stream's access area (at most 1,000 newest rows) and the access
audit log's entries, turns each into an entry with a kind, who, whom or what and a sentence, sorts newest first,
filters by kind and person, and pages by 25. The tab renders it on the server with a GET filter form. New activity
kinds: `access.role.refused` (with the blockers) and `access.request.expired`.

## R9. Names

**Decision**: `Corex\Access\AbilityNames` maps every shipped ability key to a translated name and description,
every area to a name and every risk to a word, falling back to the catalogue's text for an ability registered by
someone else. The approval phrase stays the ability's key (DECISIONS #376), shown as the phrase to type.
