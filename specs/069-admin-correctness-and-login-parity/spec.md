# Feature Specification: Admin Correctness & Login Hiding Parity

**Feature Branch**: `fix/069-admin-correctness-and-login-parity`

**Created**: 2026-07-16

**Status**: Draft

**Input**: User description: "the LOGIN POLICY it is not usefull and doesn't behave like wp hide login plugin as i asked before; review the margins and padding between texts; the select box still have an issue in the hover effect in the darkmode; the insights page design is not good, need to be more decent, the cards and grids needs to be reordered; page=corex-data is the same as records tab inside corex-data-models page; i should be able to filter using forms as a select box, list all the form names and i filter using it beside writing the name, same as page=corex-submissions; the page=corex-data-models in the models tab needs to be an accordion"

## Context

Spec 068 closed the product-functional-completion work and merged, but an owner review of the running admin found seven defects the audit did not catch. This spec covers their correction. The defects are not new features — they are the difference between what 068 claimed to deliver and what it actually delivers.

The owner previously asked for login hiding that behaves like the WPS Hide Login plugin. What shipped announces its own presence, contradicts itself, and can lock the owner out of their own site.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The hidden login is genuinely hidden (Priority: P1)

A site owner turns on login protection and sets a custom login address. From then on, an anonymous visitor who probes the well-known WordPress login and admin addresses learns nothing: every probe looks exactly like a page that was never there. The owner reaches their site through the custom address, and every login-related journey — signing in, signing out, recovering a password, registering — carries them through that address rather than the hidden one.

**Why this priority**: This is the defect the owner has now reported twice. A login screen that is hidden in a way an attacker can detect is not hidden; it is merely inconvenient for the owner. Every other item in this spec is cosmetic or organisational by comparison.

**Independent Test**: Enable protection on a real install, then probe the default endpoints as an anonymous visitor and compare the responses byte-for-byte against a genuinely nonexistent URL. Sign in through the custom address and complete a logout/password-reset round trip.

**Acceptance Scenarios**:

1. **Given** protection is enabled with a custom address, **When** an anonymous visitor requests the default login address, **Then** they receive the site's ordinary "page not found" response — indistinguishable from requesting a URL that never existed.
2. **Given** protection is enabled, **When** an anonymous visitor requests the admin area, **Then** they receive the same ordinary "page not found" response rather than a redirect that reveals the custom address.
3. **Given** protection is enabled, **When** an anonymous visitor requests any of the conventional admin shortcuts, **Then** those too resolve to "page not found" rather than leaking the login location.
4. **Given** protection is enabled, **When** the owner visits the custom address, **Then** the login screen renders correctly and without warnings.
5. **Given** protection is enabled, **When** any part of the site tries to send a visitor to the default login address, **Then** the visitor arrives at the custom address instead.
6. **Given** protection is enabled and a user is already signed in, **When** a flow requires the login screen (a password-protected post, or a re-authentication prompt), **Then** that flow still completes — carried to the custom address, with the default address staying hidden even from them.

---

### User Story 2 - The owner cannot be locked out (Priority: P1)

An owner configuring login protection cannot, by any sequence of choices available in the interface, arrive at a state where the default login is hidden but no working alternative exists. Addresses that would collide with existing content, or that are too short or otherwise unusable, are refused at the moment of saving with an explanation. After saving, the owner is shown the address they must now use and is warned before the change takes effect.

**Why this priority**: The current implementation has two live lockout paths — an address that reduces to nothing, and an address short enough to crash the site outright. Both are reachable from the interface. Shipping any further login work without closing them is negligent.

**Independent Test**: Attempt to save empty, too-short, and reserved addresses; confirm each is refused with a clear reason and that the site remains reachable throughout.

**Acceptance Scenarios**:

1. **Given** the owner enters an address that reduces to nothing after cleanup, **When** they save, **Then** the save is refused with an explanation and the previous working configuration is untouched.
2. **Given** the owner enters an address that is too short or collides with existing content, **When** they save, **Then** the save is refused with an explanation naming the conflict.
3. **Given** a configuration was somehow stored in an unusable state, **When** any page of the site loads, **Then** the site still loads and no unrelated capability is lost — an unusable stored value never crashes the site nor silently removes other features.
4. **Given** protection is switched on but cannot be applied, **When** a visitor requests the default login, **Then** the system does not quietly serve it while still reporting itself protected — the owner is told.
4. **Given** the owner saves a valid configuration, **When** the save completes, **Then** the resulting login address is displayed prominently and the owner is warned that the default is now hidden.
5. **Given** an owner has lost access, **When** they use the documented recovery path, **Then** access is restored.

---

### User Story 3 - Records live in one place (Priority: P2)

Someone looking for stored records finds exactly one place to look. The former standalone Data screen no longer exists as a separate destination; anyone arriving at its old address — from a bookmark, a link, or muscle memory — lands on the records view inside Data Models without noticing the seam. Individual views within Data Models can be linked to directly.

**Why this priority**: Two navigation entries rendering the identical screen is a comprehension tax on every user, but nothing is broken or unsafe while it persists.

**Independent Test**: Visit the old Data address and confirm arrival at the records view; share a link to a specific view and confirm it opens there.

**Acceptance Scenarios**:

1. **Given** a user opens the old Data address, **When** the page loads, **Then** they arrive at the records view within Data Models.
2. **Given** a user is on a particular view within Data Models, **When** they copy the address and open it fresh, **Then** the same view opens.
3. **Given** a user could previously reach records, **When** the Data screen is removed, **Then** they can still reach records with no loss of permission.

---

### User Story 4 - Filtering by form name, not by number (Priority: P2)

Someone reviewing submissions or records filters by choosing a form from a list of its real names. They are not asked to know or type an internal number, and they can still search by free text when they prefer.

**Why this priority**: The submissions screen currently demands a raw numeric identifier that no user can be expected to know, which makes its most obvious filter effectively unusable. But the screens do function.

**Independent Test**: With several forms present, choose each from the list on both screens and confirm the results narrow to that form.

**Acceptance Scenarios**:

1. **Given** forms exist, **When** a user opens the filters on either screen, **Then** they see a list of the real form names.
2. **Given** a user selects a form, **When** the results refresh, **Then** only that form's entries remain.
3. **Given** a user prefers typing, **When** they use free-text search, **Then** it still works alongside the list.
4. **Given** no forms exist, **When** a user opens the filters, **Then** the control communicates that plainly rather than appearing broken.
5. **Given** the forms capability is entirely absent, **When** the screens load, **Then** they still work — the filter degrades rather than failing.

---

### User Story 5 - Models are scannable (Priority: P3)

Someone surveying registered models sees a compact list they can scan at a glance, expanding only the ones they care about. The list is fully operable by keyboard and announced correctly to assistive technology.

**Why this priority**: A quality-of-life improvement on a working screen.

**Independent Test**: Navigate the list by keyboard alone; expand and collapse entries; verify announcements with a screen reader.

**Acceptance Scenarios**:

1. **Given** several models are registered, **When** the view opens, **Then** entries are collapsed with the first expanded.
2. **Given** a user is on an entry, **When** they operate it by keyboard, **Then** it expands and collapses and its state is announced.

---

### User Story 6 - The admin reads cleanly (Priority: P3)

Text throughout the admin sits on a consistent rhythm — headings, paragraphs, and helper text are spaced the same way wherever they appear. Selection controls are legible in dark mode, including the highlight on the option under the pointer. The insights screen presents its cards in a single coherent grid ordered by what most needs attention.

**Why this priority**: Presentation defects, but the owner's standing mandate treats approved design as required functionality, so they are in scope rather than deferred.

**Independent Test**: Compare spacing across screens; open a selection control in dark mode in a real browser; confirm the insights ordering puts urgent items first.

**Acceptance Scenarios**:

1. **Given** any admin screen, **When** headings, paragraphs, and helper text render, **Then** their spacing is consistent across screens.
2. **Given** dark mode is active by pin or by system preference, **When** a user opens a selection control, **Then** the highlighted option is legible.
3. **Given** the insights screen, **When** it loads, **Then** its cards form one coherent grid ordered with the most urgent first and unavailable items last.
4. **Given** a right-to-left locale or light mode, **When** any changed screen renders, **Then** it remains correct.

### Edge Cases

- An address that reduces to nothing, is too short, or collides with existing content — refused at save (US2).
- A stored address that is already unusable — the site must still load rather than crash (US2.3).
- A user already signed in requesting the hidden default login — hidden from them too. This is only safe because every login-bearing URL is rewritten (FR-004), so nothing legitimate points at the default address; verify the rewrite before relying on the hiding.
- Background and system requests that legitimately reach admin addresses must never be hidden, or scheduled work and asynchronous features break.
- Multi-site installations: invitation messages and signup addresses must carry the custom address, not the hidden one.
- Forms capability absent entirely — filters degrade rather than fail (US4.5).
- No forms yet defined — the filter says so plainly (US4.4).
- A selection control whose option highlight the browser will not allow styling — must be resolved by other means rather than shipping a rule that silently does nothing.

## Requirements *(mandatory)*

### Functional Requirements

**Login hiding (US1)**

- **FR-001**: When protection is enabled with a custom address, the system MUST respond to anonymous requests for the default login and admin addresses with the site's ordinary "not found" response, indistinguishable from a genuinely absent page.
- **FR-002**: The system MUST apply this hiding before any other component can act on or reveal the request.
- **FR-003**: The system MUST decide whether to hide a request using exactly one rule set, with no second rule set able to reach a different verdict.
- **FR-004**: The system MUST rewrite every reference to the default login address — including redirects issued by any component — to the custom address, preserving any accompanying parameters and the request's security scheme.
- **FR-005**: The system MUST prevent conventional admin shortcut addresses from revealing the login location.
- **FR-006**: Flows that need the login screen while a user is already signed in — a password-protected post, a re-authentication prompt, signing out — MUST keep working. They MUST do so by being carried to the custom address (FR-004), not by exposing the default one, which stays hidden from everyone.
- **FR-007**: The system MUST NOT hide background, asynchronous, or system requests.
- **FR-008**: On multi-site installations, the system MUST carry the custom address into invitation messages and signup addresses.

**Lockout prevention (US2)**

- **FR-009**: The system MUST refuse to save an address that is empty after cleanup, shorter than the permitted minimum, reserved, or colliding with existing content — returning an explanation naming the reason.
- **FR-010**: The system MUST apply identical cleanup rules when storing and when reading an address, so a saved value always reads back as the same working value.
- **FR-011**: The system MUST NOT allow an unusable stored address to prevent the site from loading, nor to disable any capability beyond the address itself.
- **FR-011a**: Protection MUST NOT fail open. If protection is switched on but cannot be applied, the system MUST NOT silently serve the unprotected default while continuing to report itself as protected — the discrepancy MUST be surfaced where the owner will see it, not only in a log that is disabled by default.
- **FR-012**: The system MUST display the resulting login address after a successful save, and MUST warn before the default becomes hidden.
- **FR-013**: The system MUST provide a recovery path that restores access without database access, and MUST document it where an owner locked out of the admin can still find it.
- **FR-014**: The interface's default values MUST match the stored defaults, so an unsaved form never misrepresents current state.

**Honest controls (US2)**

- **FR-015**: The system MUST populate the lockout display from real recorded data.
- **FR-016**: Every control in the security area MUST perform a real action; a control that cannot MUST be removed rather than left inert.

**Records consolidation (US3)**

- **FR-017**: The system MUST remove the standalone Data destination and route its former address to the records view, preserving existing links.
- **FR-018**: The system MUST allow each view within Data Models to be addressed and shared directly.
- **FR-019**: The consolidation MUST NOT reduce any user's existing access to records.

**Form filtering (US4)**

- **FR-020**: Both the submissions and records screens MUST offer a filter listing real form names.
- **FR-021**: Free-text search MUST remain available alongside the list.
- **FR-022**: Each screen's filter MUST match how that screen actually stores its association to a form — these differ between the two screens and MUST NOT be conflated.
- **FR-023**: The filter MUST communicate an empty list plainly, and MUST degrade rather than fail if the forms capability is absent entirely.

**Models presentation (US5)**

- **FR-024**: Registered models MUST be presented as expandable entries, first expanded, remainder collapsed, fully keyboard-operable with state announced.

**Presentation (US6)**

- **FR-025**: Headings, paragraphs, and helper text MUST derive spacing from one shared definition rather than per-screen repetition.
- **FR-026**: A selection control's highlighted option MUST be legible in dark mode, verified in a real browser rather than by inspecting rules.
- **FR-027**: The insights screen MUST present one coherent grid, ordered by urgency, with unavailable items last.
- **FR-028**: All changed screens MUST remain correct in right-to-left locales and in light mode, and MUST meet WCAG 2.2 AA.
- **FR-029**: Changed stylesheets MUST be versioned so that a returning visitor is never served a stale copy.

### Key Entities

- **Login protection configuration**: whether protection is on, the custom address, whether defaults are hidden, and the attempt-throttling parameters. Persisted per site.
- **Login attempt / lockout record**: a recorded authentication attempt and any resulting lockout, with an identity, a time, and an expiry. Already stored; not currently surfaced.
- **Form (flow)**: a defined form, with a stable identifier, a human name, and a slug. Owned by the forms capability; read here without becoming a hard dependency.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: With protection enabled, an anonymous probe of the default login address and of every conventional shortcut is indistinguishable from a probe of a genuinely absent page — verified by comparing status and body against a control URL. The admin address returns the same ordinary "not found" response; see the known limitation below for the one respect in which it is not byte-identical.
- **SC-002**: Signing in, signing out, recovering a password, registering, and re-authenticating all complete through the custom address with zero warnings or errors.
- **SC-003**: No sequence of choices available in the interface produces a state where the default login is hidden and no working alternative exists — verified by exercising empty, too-short, reserved, and colliding addresses.
- **SC-004**: An unusable stored address never prevents a page from loading, never removes an unrelated capability, and never results in the default login being served while the owner is still told protection is on.
- **SC-005**: Every control in the security area performs a real action; the lockout display reflects real recorded data.
- **SC-006**: Exactly one destination presents records, and every previously working link still arrives somewhere correct.
- **SC-007**: A user can filter to a specific form on both screens by choosing its name, with no knowledge of internal identifiers, and results narrow correctly on both.
- **SC-008**: Both screens continue to function with the forms capability entirely absent.
- **SC-009**: Models are fully operable by keyboard alone, with expansion state announced.
- **SC-010**: Spacing between headings, paragraphs, and helper text is consistent across every admin screen.
- **SC-011**: A selection control's highlighted option is legible in dark mode in a real browser, in both pinned and system-preference dark, and correct in light and right-to-left.
- **SC-012**: The insights screen presents one grid ordered by urgency, with unavailable items last.
- **SC-013**: All existing automated checks remain green, with new coverage for the single hiding rule, address validation, the "not found" response, the lockout display, and the real hide-and-serve behaviour end-to-end.

## Known limitation — the admin address's response is not byte-identical

Measured on the real install after the emoji-shim fix: a genuinely absent page returns **79,968 bytes**; the hidden default login returns **79,968 bytes** (byte-identical, as FR-001 requires); the hidden admin address returns the same "not found" page with the same 404 status but **46,587 bytes**, because it carries fewer inline styles.

**Corrected since the first measurement.** The hidden admin response previously measured ~57,000 bytes, and part of that was a *defect, not payload*: it printed `Function print_emoji_styles is deprecated since version 6.4.0!` into its own body. Core unhooks that shim from `wp_enqueue_emoji_styles()`, choosing its target with `is_admin() ? 'admin_print_styles' : 'wp_print_styles'`; `WP_ADMIN` cannot be unset, so on a hidden admin request core inspected the wrong hook, never unhooked, and the deprecated function ran. A visible PHP diagnostic announces the hiding far more loudly than the 404 conceals it, so this was never within the limitation — it was a bug, and it is fixed by moving the shim to the hook core actually inspects. That also lets core enqueue the modern `wp-emoji-styles` block, which the hidden response was missing; both responses now carry it. The remaining difference is entirely the per-block core stylesheets.

**Why the rest cannot be closed by this work.** `wp_should_load_separate_core_block_assets()` returns `false` on `is_admin()` *before* its own filter runs, so no plugin can reach it, and whether the front-end styling pipeline registers at all is decided at `init` — while the request is still identified as an admin one. The only earlier moment available is before WordPress knows whether the visitor is signed in, so a fix there would have to treat *every* admin request as a front-end one, which would break the admin for the people entitled to use it. The response is a real "not found" page produced by the site's own routing; only its styling payload differs.

**What it costs.** Someone comparing the byte size of two "not found" responses can still infer that the admin address is handled specially, and therefore that a login is hidden somewhere. They do not learn where: the address itself is never disclosed.

**Why it is still a large improvement.** What shipped before disclosed the custom address outright to anyone requesting `/login`, and answered the hidden endpoints with a 2,515-byte page that no real "not found" ever produces. Both are closed.

**Options if this matters** (owner decision, not assumed here): block the admin address at the web server or edge before WordPress runs, which removes the difference entirely; or accept it as the residual cost of hiding an address inside the application.

## Assumptions

- **The reference behaviour is the WPS Hide Login plugin**, as named by the owner. Where this spec is silent on a login-hiding detail, that plugin's observable behaviour is the intended answer.
- **Hiding is obscurity, not authentication.** It reduces automated probing; it is not a substitute for the throttling and credential controls that already exist. It is scoped accordingly and is not claimed as an access control.
- **Reserved-address rules** cover existing content addresses and WordPress's own reserved terms. A minimum length is enforced because the stored value's own validation already requires one.
- **Insights urgency ordering** derives from each card's existing state, not from a new priority field.
- **The consolidated records destination inherits the Data Models permission.** The two screens carried different permissions; the surviving one governs. Any user who could reach records before must still reach them (FR-019) — if the permissions do not already align for every such user, that gap surfaces during implementation and is resolved rather than silently narrowed.
- **The forms capability is optional** (Principle IX) and is read through the existing lazy-resolution pattern, never required.
- **Verification happens on the real WordPress install** at `corex.local`, per the Environment Gate. Login changes are verified with the recovery path confirmed working *first*.
- **Native selection-control option highlights are partly outside CSS control.** If a browser will not honour the styling, the control is replaced with the existing accessible alternative rather than shipping a rule that does nothing.
- **The duplicated stylesheet source files** discovered during planning (byte-identical copies referenced by nothing) are out of scope; they are flagged for a separate decision rather than removed here.
