# Feature Specification: Abilities that mean something

**Feature Branch**: `spec/117-abilities`

**Created**: 2026-10-23

**Status**: Approved (decided under the owner's 2026-10-21 mandate)

**Input**: Release R3.1 of the CoreX revamp, stage 3. The owner delegated the revamp on 2026-10-21 and asked
not to be consulted; every decision below that the stories left open takes the planning workspace's recommended
answer and is recorded as "decided under the owner's 2026-10-21 mandate". Seven stories, carried as this spec's
acceptance criteria: US-ACC-01, US-ACC-02, US-ACC-03, US-ACC-04, US-ACC-05, US-SHL-06 and US-EML-20. The
stories, their test cases and the verification behind them are kept in the owner's private planning workspace.

## Why this spec exists

CoreX has fifteen abilities (Manage forms and flows, Manage Email Studio, Manage settings, and so on) that an
owner can allow for a role in the role matrix, or grant to one person by approving their request. Most CoreX
screens do not ask for them: they open for site administrators only, so an Allow or an approved request opens
nothing there. This release makes every CoreX screen, its routes and its save actions ask for the ability that
names it, and makes the Access & Abilities screen describe that model truthfully: a grid of what each role can
really do, counts of CoreX abilities, one history of access events, and every ability named in the site's
language.

Spec 112 (R0.1, v0.47.0) prepared for this: requests can no longer ask for the abilities that control the whole
site, every grant made by approval is listed with its note and expiry and can be revoked, and refusals are counted.
This spec builds on its `AccessService`, `RequestableAbilities` and grants list.

## Clarifications

### Decided under the owner's 2026-10-21 mandate

- Q: May the role matrix take a CoreX ability away from a role that can administer the site? → A: **No
  (ACC-D11).** A site administrator (anyone who can manage the site's options; on a network, a super
  administrator too) always holds every CoreX ability. Their column reads "Allowed: site administrator" and offers
  no Deny. A Deny stored before this release is kept, not applied, and listed once on the Access screen.
- Q: Move every screen to its ability, or limit the matrix to what is enforced? → A: **Move them (ACC-D7).**
- Q: What lets a person see a CoreX screen without changing it? → A: **One new ability, "See CoreX screens without
  changing them" (SHL-D3)**, held by no role by default and granted in Access & Abilities. It opens Overview,
  Settings, Operations & Security and Insights read-only; every other screen keeps the full refusal.
- Q: Blog Pro opens for anyone who can edit posts since spec 112, which said stage 3 would give it its own
  ability. Which wins? → A: **Its own ability, "Manage Blog Pro"**, as US-ACC-01 lists and spec 112 anticipated
  (DECISIONS #379). Inside Blog Pro, each post and comment still follows WordPress's own permission for it.
- Q: Which actions in Email Studio "change how the whole site delivers mail"? → A: **Choosing the delivery
  provider and turning live delivery on or off**, which live in Settings → Mail and are reached from Email Studio's
  "Configure delivery". They keep needing site administration; a holder of "Manage settings" or "Manage Email
  Studio" sees them read-only with a sentence saying why. CoreX stores no mail provider secret today.
- Q: Where are refusals of a route or a save recorded? → A: **In the access audit, counted like page refusals
  (spec 112)**, under the screen they belong to.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Every CoreX screen opens for the people given its ability (Priority: P1)

A site owner gives a team member one area of CoreX, such as Forms or Email Studio, from the role matrix or by
approving their request, and that area opens for them. The owner does not have to make everyone an administrator.

**Why this priority**: it is what the matrix and the request workflow promise; without it the other stories
describe abilities that do nothing.

**Independent Test**: allow "Manage forms and flows" for the Editor role, sign in as an editor, and open the
CoreX screens one by one and call their routes.

**Acceptance Scenarios** (US-ACC-01):

1. **Given** the release, **When** any CoreX screen, its routes or its save actions are used, **Then** each checks
   the ability of its screen: Forms & Flows "Manage forms and flows"; Email Studio "Manage Email Studio"; Settings
   and the captcha test "Manage settings"; Operations & Security and the login protection settings "Manage
   operations and security"; Access & Abilities "Manage access"; Blog Pro "Manage Blog Pro"; Setup Wizard "Manage
   setup"; Overview, Add-ons and Insights "Access CoreX admin". Submissions, Data, Notifications and Releases keep
   the abilities they already check; Guides keeps opening for anyone signed in.
2. **Given** an Editor allowed "Manage forms and flows", **When** they sign in, **Then** the CoreX menu shows Forms
   & Flows and Guides, Forms & Flows opens and works, and any other CoreX screen answers with the access-denied
   page and HTTP 403.
3. **Given** the same Editor, **When** Forms & Flows is opened by its address or its routes are called, **Then**
   each answers; **When** Settings' routes are called, **Then** they answer 403.
4. **Given** someone allowed "Manage operations and security" only, **When** they switch the site to Production or
   Maintenance, **Then** it is refused, saying "Run dangerous actions" is also needed; switching to Staging works.
5. **Given** a site administrator, **When** they use any CoreX screen or action, **Then** it works exactly as
   before the release.
6. **Given** the role matrix, **Then** a role that can administer the site reads "Allowed: site administrator" on
   every ability and offers no Deny; a Deny stored for such a role before the release is not applied and is listed
   once on the Access screen with a sentence saying it is ignored.
7. **Given** the Data screen, **Then** the models that hold access requests, role grants or sign-in attempts are
   readable only by people who also hold "Manage access" or "Manage operations and security" (already true since
   spec 112; kept).
8. **Given** the Submissions screen, **Then** the retention forms appear only to people who may use them: saving
   needs "Manage settings", pruning needs "Run dangerous actions".
9. **Given** the Access screen, **Then** its requirements card lists each screen with the ability it needs, and no
   text on it or on the denied page says CoreX screens require `manage_options`.
10. **Given** the release notes, **Then** they say no administrator loses access, that grants made earlier start to
    work, and that owners should review CoreX → Access & Abilities (its grants list) first and check for a Deny
    stored on the Administrator role.

### User Story 2 - The role matrix shows what each role can really do (Priority: P2)

A site owner reads one grid of CoreX abilities by role, with where each answer comes from, before changing it.

**Independent Test**: allow an ability for Editor, deny one for Subscriber, open the Role matrix tab.

**Acceptance Scenarios** (US-ACC-02):

1. **Given** the Role matrix tab, **Then** it opens on a grid: CoreX abilities down the side grouped by area,
   roles across the top.
2. **Then** each cell shows one of: Allowed (set here), Denied (set here), Allowed through site administration
   (inherited), Not granted (inherited), Locked; each as text and an icon, and a screen reader announces the same
   words with the row and column.
3. **Then** the legend lists those five states.
4. **When** "Edit" is pressed on a role's column, **Then** today's editor for that role opens, with its preview and
   confirmation unchanged, and the grid shows the new state after the change is applied.
5. **When** a Locked cell is focused or hovered, **Then** it says why.
6. **Then** the WordPress capability grid is no longer on this tab.
7. **Given** a role plugin is active, **Then** the grid says CoreX manages only its own abilities.
8. **Given** a 400px wide window, **Then** the grid scrolls inside its own frame with the ability names kept in
   view, and the page does not scroll sideways.

### User Story 3 - The Access overview counts CoreX abilities (Priority: P2)

**Acceptance Scenarios** (US-ACC-03):

1. Each role card shows how many CoreX abilities the role holds out of all of them, counted the way the matrix
   resolves them (Administrator: all).
2. The groups card lists every CoreX ability by area, with its risk in words (Sensitive, Dangerous, Critical) and
   whether it is locked.
3. "Your permissions" lists the CoreX abilities you hold and how you hold them: your role, site administration, or
   granted to you (until a date, when the grant has one).
4. A role WordPress does not ship is labelled "Added by a plugin or the site", not "Custom".
5. The sentence "CoreX invents none" is gone.
6. Counts use the site's number format and plural forms.

### User Story 4 - The access audit shows every access event, grouped and paged (Priority: P2)

**Acceptance Scenarios** (US-ACC-04):

1. The Audit log tab lists, newest first: role changes (with what changed), requests made, approved, denied,
   cancelled and expired, grants revoked and expired, refused role changes with the reason, and refused visits and
   actions counted as spec 112 stores them.
2. Each entry names who did it, to whom or what, and when, with a kind label in text.
3. Entries are grouped by day, 25 per page, and can be filtered by kind and by person.
4. The empty state lists only what is recorded, and says how long entries are kept.
5. The second list on the Role matrix tab is removed.
6. Viewing the tab needs "Manage access".

### User Story 5 - Ability names are translated and keys stay out of sight (Priority: P2)

**Acceptance Scenarios** (US-ACC-05):

1. Every ability's name and description, and every area's name, is translatable and appears in the site's
   language.
2. The denied page names the access a request asks for by its translated name ("Manage access").
3. A refused change names the ability by its translated name and says why in words.
4. The internal key is shown only as secondary detail (a tooltip), never as the main text.
5. In right-to-left, names, states and controls stay aligned in their columns.

### User Story 6 - Someone allowed to look, but not to change, sees a CoreX screen read-only (Priority: P2)

**Acceptance Scenarios** (US-SHL-06):

1. A person holding "See CoreX screens without changing them" (granted in Access & Abilities, held by no role by
   default) opens Overview, Settings, Operations & Security and Insights read-only.
2. On a read-only screen every control that changes something is disabled and says why when focused, a "Read only"
   chip sits in the header, and a sentence names who can make changes and how to ask for access.
3. Secrets stay hidden exactly as for an administrator; nothing read-only shows more than the full screen shows.
4. A change attempted anyway (a request made by hand to the screen's route or form) is refused with the standard
   refusal and recorded in the access audit.
5. A person with neither the view nor the manage ability gets the access-denied page.

### User Story 7 - Email Studio opens for the people given "Manage Email Studio" (Priority: P2)

**Acceptance Scenarios** (US-EML-20):

1. A person with "Manage Email Studio" opens Email Studio and uses its tabs and routes; a person without it is
   refused with the standard access-denied page or answer.
2. Site administrators keep Email Studio.
3. Choosing the delivery provider and turning live delivery on or off keep needing site administration; a holder of
   the ability sees them read-only with a sentence saying why.
4. A failure notification links to Email Studio only for people who can open it.
5. The role matrix lists Email Studio under the ability.
6. Refusals are recorded in the access audit, and the release notes say that past grants of the ability start
   opening the screen.

### Edge Cases

- A person who holds abilities but not "Access CoreX admin" opens the CoreX menu: WordPress points the menu at the
  first CoreX screen they may open, and the CoreX rail lists only the screens they may open.
- A person who holds both the view ability and a screen's manage ability gets the full screen.
- A role that can administer the site and has a stored Allow: nothing changes; it holds everything anyway.
- A stored Deny for a role that can administer the site that is later set to Inherit is removed from the list.
- A grant whose ability is denied by the person's role: the role's Deny still wins for anyone who is not a site
  administrator, as before.
- A Locked ability ("Access CoreX admin") comes only with site administration; no role can be allowed it.
- On a network, a super administrator passes every check, as WordPress already ensures.
- Without scripts, a read-only screen still renders, and its saves are still refused on the server.

## Requirements *(mandatory)*

### Functional Requirements

**Screens check their abilities (US-ACC-01, US-EML-20)**

- **FR-001**: CoreX MUST keep one map from each CoreX screen to the ability it needs and whether it can be opened
  read-only. The menu entry, the screen's render check, its REST routes, its form handlers and the denied page's
  request MUST all be answered from it.
- **FR-002**: The screens and routes named in US-ACC-01 scenario 1 MUST check that ability instead of site
  administration; the captcha test and the login protection settings follow their screen; the activity routes and
  the add-on activation notice follow "Access CoreX admin"; the Coming soon toolbar link follows "Manage
  operations and security"; the Guides for Overview, Add-ons, Performance and Insights require their screen's
  ability.
- **FR-003**: Switching the operating mode to Production or Maintenance MUST also need "Run dangerous actions"; the
  refusal names it.
- **FR-004**: The Submissions screen MUST show the retention save form only to holders of "Manage settings" and the
  prune form only to holders of "Run dangerous actions"; their handlers MUST check the same.
- **FR-005**: A refused route call or save of a screen in FR-002 MUST answer 403 with a translated sentence and be
  recorded in the access audit under that screen, counted as spec 112 counts refusals.
- **FR-006**: The CoreX rail MUST list only the CoreX screens the person may open, also when WordPress has pointed
  the CoreX menu at another first screen.
- **FR-007**: In Email Studio, a person who cannot administer the site MUST NOT be offered "Configure delivery";
  a sentence says that delivery and its provider are chosen by a site administrator in Settings.
- **FR-008**: In Settings → Mail, the delivery provider and live delivery fields MUST be read-only, with a sentence,
  for anyone who cannot administer the site, and a save by such a person MUST leave both as they were.

**Site administrators keep everything (US-ACC-01, ACC-D11)**

- **FR-009**: Anyone who can administer the site MUST hold every CoreX ability whatever the role matrix stores.
- **FR-010**: A role change that sets Deny on a role that can administer the site MUST be refused, naming the
  ability and saying that site administrators always hold every CoreX ability.
- **FR-011**: The Access screen MUST list, once, each Deny stored for such a role, with a sentence saying it is
  kept and not applied.

**The view ability (US-SHL-06)**

- **FR-012**: A new ability, "See CoreX screens without changing them", MUST exist, held by no role by default,
  implied by "Access CoreX admin", and requestable.
- **FR-013**: A holder who lacks a screen's manage ability MUST get Overview, Settings, Operations & Security and
  Insights in read-only mode: a "Read only" chip in the header, a sentence naming the ability that makes changes
  and who holds it, a way to request it (or, where it cannot be requested, that an administrator grants it through
  the role matrix), and every control that changes something marked disabled and described on focus.
- **FR-014**: Those screens' read routes MUST answer the holder; their write routes and form handlers MUST refuse
  and record the refusal.
- **FR-015**: A read-only screen MUST NOT show any value the full screen hides (secrets stay write-only).

**The matrix and the overview (US-ACC-02, US-ACC-03)**

- **FR-016**: The matrix MUST resolve each role × ability cell to exactly one of the five states of US-ACC-02 and
  send that state, the role's administers-the-site flag and why a cell is locked.
- **FR-017**: The Role matrix tab MUST show the grid, grouped by area, with a legend, a per-role Edit that opens the
  existing editor, cells announced in words, locked cells explained on focus and hover, and a frame that scrolls
  on its own with the ability column kept in view.
- **FR-018**: The WordPress capability grid, the matrix tab's own audit list, the "tracked capability groups" card
  and every sentence saying CoreX screens require `manage_options` or that CoreX invents no abilities MUST be gone.
- **FR-019**: The overview's role cards, groups card and "Your permissions" MUST count and list CoreX abilities as
  US-ACC-03 says, with the site's number format and plural forms.

**The audit (US-ACC-04)**

- **FR-020**: A role change refused by the safety rules MUST be recorded in the activity stream's access area with
  its reasons; a request that expires when someone tries to decide it MUST be recorded too.
- **FR-021**: The Audit log tab MUST merge the access area of the activity stream with the counted refusals, newest
  first, grouped by day, 25 per page, filterable by kind and by person, each entry with who, what or whom, when and
  a kind label.
- **FR-022**: Its empty state MUST say what is recorded and for how long (activity: 180 days; refusals: 30 days).

**Names (US-ACC-05)**

- **FR-023**: Every CoreX ability's name and description, every area's name and every risk MUST be shown through
  the translation functions, by one shared lookup.
- **FR-024**: The denied page, the refusal of a role change, the matrix, the overview, the approval dialog and the
  grants and requests lists MUST show the translated name; a key appears only as a tooltip.

### Key Entities

- **Screen ability**: a CoreX screen's page slug, its section key, the ability it needs, and whether it opens
  read-only for the view ability.
- **Cell state**: one of `allowed_set`, `denied_set`, `allowed_admin`, `not_granted`, `locked`, for a role and an
  ability.
- **Access history entry**: when, kind, who did it, to whom or what, a sentence, and for refusals a count and range.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An editor allowed one CoreX ability opens exactly the CoreX screens that ability names, plus Guides;
  every other CoreX screen and route answers 403 (measured on every CoreX screen and one route of each).
- **SC-002**: A site administrator opens all fifteen CoreX screens and uses one action on each, with a Deny stored
  on the Administrator role, with no difference from the previous release.
- **SC-003**: The Role matrix tab shows every ability and role in one grid, every cell in one of five states, and at
  400px the page has no horizontal scroll.
- **SC-004**: The audit tab shows at most 25 entries a page and every recorded access event kind of FR-021.
- **SC-005**: No `corex_` key appears as the main text of a sentence on the Access screen or the denied page.
- **SC-006**: A holder of only the view ability opens the four read-only screens, sees no secret, and every attempt
  to save through a route or a form is refused and recorded.

## Client impact

Every CoreX screen starts checking its own ability. Site administrators see no change on Muva or Perego. Grants made
earlier, by approving a request or by an Allow in the role matrix, start opening their screens: each client's owner
reviews CoreX → Access & Abilities first (its grants list) and checks whether a Deny is stored on the Administrator
role (it is listed on the Overview tab and ignored). Blog Pro now opens for holders of "Manage Blog Pro" and site
administrators; editors and writers who used it since v0.47.0 need "Manage Blog Pro" allowed for their role. Email
Studio opens for holders of "Manage Email Studio". Mizzey: not on this release.

## Assumptions

- A CoreX ability reaches a role only through the role matrix or site administration, and a person through an
  approved request; abilities written into a role by a role plugin are not shown as a separate state.
- The data, submissions and widgets checks that already accept the ability or site administration are left as they
  are: with FR-009 the ability alone answers the same.
- The read-only treatment is applied by a small script over the screen's controls; without scripts the controls are
  plain but every save is still refused on the server.
- The activity stream keeps access events for 180 days and the refusal log for 30 days, as today.
