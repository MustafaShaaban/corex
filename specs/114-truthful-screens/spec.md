# Feature Specification: Screens that tell the truth

**Feature Branch**: `claude/spec-114-truthful-screens-43137f`

**Created**: 2026-10-21

**Status**: Draft

**Input**: The owner approved, on 2026-10-20, the stage 0 stories of the CoreX revamp as written. This spec is its
third release, R0.3, and carries nine of them as its acceptance criteria: US-SAFE-10, US-SAFE-11, US-SAFE-12,
US-SAFE-13, US-SAFE-16, US-SAFE-08, US-SET-10, US-BLG-05 and US-SET-14 (the last one brought into stage 0 by the
approved change request CR-024). The stories, their test cases and the reading of the code behind them are kept in
the owner's private planning workspace.

## Why this spec exists

Several CoreX admin screens say something that is not so: the Overview calls a site with no logo "Brand configured",
the Security Center says the login address is hidden while login protection is off, a PageSpeed run that never
reached the site is graded D, the security events card cannot see most security events, the captcha test button
never appears, the Add-ons screen reports "Enabled" for an add-on that failed to turn on, the login screen offers a
single sign-on button that does nothing, Blog Pro implies it records visits, and saving one Settings tab can switch
off settings on another. Each is small and contained. They ship together as one patch release because an owner who
finds one false state stops trusting every other state the admin shows.

## Clarifications

### Session 2026-10-21 (decided under the owner's 2026-10-21 mandate)

The owner delegated the revamp and asked not to be consulted; where a story leaves a choice open, the planning
workspace's recommended answer is taken.

- Q: The Overview's Brand row needs "a site name". Which one? → A: **The Company name in Brand settings**, the name
  CoreX itself draws on the site. WordPress always has a site title, so it cannot tell whether the brand was set.
- Q: Insights "Connected" needs "a working PageSpeed key". When is a key known to work? → A: **When a key is saved and
  the latest performance run with it completed.** A saved key with no run yet reads "Key saved, not checked yet".
- Q: The captcha test can check the secret key with the provider, but not the site key, which only a visitor's widget
  uses. What does the button report? → A: **The four answers the server can give, in words**: the keys work, the
  provider refused the secret key, a key is missing, or the provider could not be reached. It says the site key is
  checked when a visitor's widget loads.
- Q: The Sharing card in Blog Pro: remove it, or make it list the share block's networks? → A: **Remove it** (BLG-D9:
  hide what is not real now). The share block keeps its own networks.
- Q: How are failed PageSpeed runs stored before this release recognised? → A: **By their shape**: score 50, no
  measures, and a summary that is one of the PageSpeed diagnostic messages (in English or in the site's language)
  repeated as the only recommendation. They are shown as failures when read; the stored rows are not rewritten or
  deleted.
- Q: Refusals are counted one entry per person, screen and hour (spec 112). How many activity events does a refusal
  make? → A: **One per new counted entry**, so a page reloaded a thousand times is one event, as it is one line in
  the access log.
- Q: Saving Settings: save only the tab being saved, or every field that changed? → A: **Every field whose value was
  changed in the form**, on any tab, and nothing else. A section whose add-on is off or not installed is never
  written. This is the narrow fix CR-024 approved; per-section save bars stay with US-SET-01 (stage 3).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The Overview shows only true states (US-SAFE-10, Priority: P1)

A site owner opens the CoreX Overview first. Every "Done", "Configured" and "Connected" on it is true, and every row
that is not done says what is missing and where to set it.

**Why this priority**: it is the first screen an owner sees; a false success there teaches them to ignore every state.

**Independent Test**: on a fresh install with no logo, captcha None, no PageSpeed key and login protection off, open
the Overview and read each row.

**Acceptance Scenarios**:

1. **Given** a fresh install (no logo, captcha None, no PageSpeed key, login protection off), **When** the owner opens
   the Overview, **Then** Brand reads "Not set" with a link to Brand settings, Insights reads "Keyless (shared quota)",
   Captcha reads "Not configured", Spam protection reads "Honeypot only" and Login protection reads "Off".
2. **Given** a company name and a logo are set, **When** the Overview opens, **Then** Brand reads "Done".
3. **Given** the latest PageSpeed run failed, **When** the Overview opens, **Then** Insights reads "Error" with the
   reason.
4. **Given** reCAPTCHA keys are saved but the captcha add-on is inactive, **When** the Overview opens, **Then** Captcha
   reads "Not configured (add-on inactive)" and Spam protection reads "Honeypot only".
5. **Given** a captcha provider is active with its keys, **When** the Overview opens, **Then** Spam protection reads
   "Honeypot and <provider>".
6. **Given** login protection is on and hides the login address, **When** the Overview opens, **Then** Login
   protection reads "On, login address hidden", and hardening warnings are in a row of their own named as hardening.
7. **Given** a fact behind a row cannot be read, **When** the Overview opens, **Then** that row reads "Could not check",
   never a success.

---

### User Story 2 - The login endpoints row says "Hidden" only when they are (US-SAFE-11, Priority: P1)

A site owner reads Operations & Security. "Default login endpoints: Hidden" appears only when an anonymous visitor
really cannot reach the default login form.

**Why this priority**: an owner relying on hiding that is off has no protection and does not know it.

**Independent Test**: on a fresh install, open the screen and request `/wp-login.php` anonymously.

**Acceptance Scenarios**:

1. **Given** login protection is off, **When** the owner opens Operations & Security, **Then** the row reads
   "Reachable", and `/wp-login.php` serves the login form.
2. **Given** protection and hiding are on and the recovery constant is not set, **Then** the row reads "Hidden", and an
   anonymous request to `/wp-login.php` does not return the login form.
3. **Given** the same with `COREX_LOGIN_UNGUARD` set, **Then** the row reads "Recovery active: the default login is
   reachable" and says how to end recovery; the login form is served.

---

### User Story 3 - A failed PageSpeed run shows an error, never a grade (US-SAFE-12, Priority: P1)

A site owner runs a performance check. A run that could not complete says "Could not run", why, and offers Retry. It
never shows a score or a grade.

**Why this priority**: "D, 50" for a site that was never measured is a false judgement of the client's site.

**Independent Test**: on a local site, run performance; then open Insights with a failed run stored by an earlier
release.

**Acceptance Scenarios**:

1. **Given** a local or private site address, **When** performance is run, **Then** no request leaves for the service,
   and the card says "Could not run", "PageSpeed cannot reach a local site", with no score and no grade.
2. **Given** a public site and a quota answer from the service, **When** performance is run, **Then** the card says
   "Could not run" with the reason and a Retry button, and the history row keeps the reason and no score.
3. **Given** one completed and one failed run in history, **Then** anything that summarises scores counts only the
   completed run.
4. **Given** failed runs stored as 50/D by an earlier release, **When** Insights is opened, **Then** each shows as a
   failure with its stored message, none shows as D or counts in a summary, and the stored history keeps the same
   number of rows.

---

### User Story 4 - Insights security events are complete (US-SAFE-13, Priority: P2)

A site owner reads the Security events card. It shows the security events that happened, whatever else happened since,
and "no security events" means there were none.

**Why this priority**: important but not misleading in the dangerous direction; the events exist in their own logs.

**Independent Test**: change the mode, be refused a screen, be locked out once and decide an access request; then
trash 30 submissions and read the card.

**Acceptance Scenarios**:

1. **Given** a clean site, **When** the mode is changed, someone is refused a CoreX screen, a sign-in is locked out and
   an access request is approved, **Then** all four are in the activity log in the operations, access or security
   area, and the card shows them newest first.
2. **Given** that, **When** 30 submissions are trashed, **Then** the four security events are still on the card.
3. **Given** no security events, **Then** the card's empty message names only what is recorded, and says from when.
4. **Given** events recorded before this release only in their own logs, **Then** they are not copied, and the card
   says from when it has records.

---

### User Story 5 - The captcha test button appears and works (US-SAFE-16, Priority: P2)

A site owner who saved captcha keys presses "Test verification" and reads whether they work, before a visitor finds a
wrong key.

**Why this priority**: it is a missing tool, not a false statement, but the screen promised it.

**Independent Test**: save reCAPTCHA keys, open Settings > Captcha, press the button.

**Acceptance Scenarios**:

1. **Given** a provider and both keys saved, **When** Settings > Captcha opens, **Then** "Test verification" sits under
   the secret key field.
2. **Given** valid keys, **When** it is pressed, **Then** "works" is said in a live region a screen reader announces;
   a wrong secret says the provider refused it; an unreachable provider says so.
3. **Given** no provider, or a provider with a key missing, **Then** no button is shown, and the field says what is
   needed.
4. **Given** a keyboard only, **Then** the button is reachable, shows focus and its result is announced.
5. The automated test finds the field by the id the real form renders.

---

### User Story 6 - Enabling or disabling an add-on reports what really happened (US-SAFE-08, Priority: P1)

A site owner toggles an add-on. The screen says when it failed, never "Enabled" for something that is off, and never
lets the owner switch off an add-on this site does not control.

**Why this priority**: a toggle that silently fails, or that switches an add-on off for a whole network, changes sites
without anyone knowing.

**Independent Test**: remove an add-on's main file and press Enable; on a multisite, open Add-ons on a sub-site with a
network-active add-on.

**Acceptance Scenarios**:

1. **Given** activation fails (the file is missing, or activation prints output), **When** Enable is pressed, **Then**
   an error carries WordPress's reason, the add-on stays off, its feature flag is not written and no kit prompt is
   shown.
2. **Given** a working add-on, **When** it is enabled and then disabled, **Then** each says its real result, as today.
3. **Given** an add-on active for the whole network, **When** a sub-site opens Add-ons, **Then** its switch cannot be
   used and says "Managed by the network administrator"; a forged request does not deactivate it.
4. **Given** a CoreX add-on installed as must-use, **Then** its switch cannot be used and says "Must-use: always on",
   and its feature flag is never deleted from this screen.
5. Each enable and disable, successful or failed, is in the activity log with who and the result.

---

### User Story 7 - The login screen shows no sign-in option that does not exist (US-SET-10, Priority: P1)

A person signing in sees only ways of signing in that work.

**Independent Test**: with the stored single sign-on setting on, open `/wp-login.php`.

**Acceptance Scenarios**:

1. **Given** the stored SSO setting is on, **When** the login screen opens, **Then** there is no SSO button and no "or"
   divider.
2. **Given** any site, **When** Settings > Brand opens, **Then** it does not offer "Enable SSO login section".
3. **Given** the stored value was "1", **Then** it is still "1" after the release and after saving Brand.
4. The login screen otherwise looks and works as before: subtitle, logo, appearance, lost password and register.
5. An automated test fails if the login screen shows a control for a provider that is not connected.

---

### User Story 8 - Blog Pro says plainly that it does not count readers yet (US-BLG-05, Priority: P1)

An editor opening Blog Pro is told that reading figures are not collected yet, instead of being told nobody visited.

**Independent Test**: open Blog Pro on a site with posts and no reading data.

**Acceptance Scenarios**:

1. **Given** no reading data, **When** Blog Pro opens, **Then** there is no "Reading signals" panel and no "Figures cover
   the last 30 days" line, and one translated line says CoreX does not collect reading data yet.
2. Nothing on the screen says or implies that visits are recorded.
3. The Sharing card is gone.
4. **Given** a post with reading data (once US-BLG-04 records it), **Then** the figures panel appears as spec 075
   specifies.
5. The editorial, comments and authors panels are unchanged.

---

### User Story 9 - Saving Settings changes only what I changed (US-SET-14, Priority: P1)

A site owner saving one Settings tab changes only the values they touched.

**Independent Test**: with Corex Media off and WebP conversion stored on, change the company name and save; then turn
Corex Media on.

**Acceptance Scenarios**:

1. **Given** Corex Media off and conversion stored on, **When** Brand is saved with a new company name, **Then** the
   stored conversion settings are unchanged, and after turning Media on its switches show on.
2. **Given** Corex Captcha not installed and captcha settings stored, **When** Mail is saved, **Then** the captcha
   settings are unchanged.
3. **Given** Media on, **When** "Convert uploads to WebP" is turned off and saved, **Then** it is saved as off.
4. **Given** an invalid value, **When** it is saved, **Then** the refused-field alert shows as today and nothing else
   changes.
5. "Settings saved." shows as today, in English and Arabic.

---

### Edge Cases

- A Settings field whose stored value is empty but is drawn with a default (the Media conversion switches) is not
  written when the owner did not change it, so the runtime default keeps applying.
- A saved secret field submitted empty keeps its value, as today.
- A network-active add-on viewed from the network's main site is still locked on the CoreX screen: switching it off is
  the network administrator's job, in the network's Plugins screen.
- An add-on that activates but prints output is treated by WordPress as an error, and the screen says so.
- A PageSpeed run on a local address makes no outbound request.
- A refusal counted inside an open hour adds no activity event; the next hour's first refusal adds one.
- An Overview fact source that throws (the forms add-on missing, the login settings unreadable) changes only its own
  row to "Could not check".

## Requirements *(mandatory)*

### Functional Requirements

**Overview (US-SAFE-10)**

- **FR-001**: Brand MUST read "Done" only when the Company name and the Admin logo are both set; otherwise "Not set",
  with a link to the Brand settings tab.
- **FR-002**: The Insights row MUST read "Error: <reason>" when the latest performance run failed; "Connected" when a
  key is saved and the latest run completed; "Key saved, not checked yet" when a key is saved and nothing has run; and
  "Keyless (shared quota)" when no key is saved.
- **FR-003**: The Captcha row MUST read "Not configured" when the driver is None or Honeypot, "Not configured (add-on
  inactive)" when the captcha add-on is not active, "Keys missing" when a provider lacks a key, and "<provider>
  configured" otherwise.
- **FR-004**: The Spam protection readiness row MUST read "Honeypot only" without an active captcha provider with
  keys, and "Honeypot and <provider>" with one. Both count as done: every CoreX form carries a honeypot.
- **FR-005**: The Login protection row MUST read the Security Center's settings: "Off", "On, login address hidden" or
  "On, login address reachable", using the same rule as FR-008.
- **FR-006**: Hardening warnings MUST have their own row, labelled "Hardening checks".
- **FR-007**: Any row whose facts cannot be read MUST read "Could not check".

**Operations & Security (US-SAFE-11)**

- **FR-008**: "Default login endpoints" MUST read "Hidden" only when login protection is on, hiding is on and the
  recovery constant is not set; "Reachable" when protection or hiding is off; and "Recovery active: the default login
  is reachable" when the constant is set and the settings would hide, with a line saying recovery ends when the
  constant is removed from `wp-config.php`.
- **FR-009**: An automated check MUST show that whenever the row says "Hidden", an anonymous request to `/wp-login.php`
  does not return the login form.

**Insights (US-SAFE-12, US-SAFE-13)**

- **FR-010**: A performance run that does not complete MUST produce a failed result: no score, no grade, a failure kind
  and the reason in plain words. Local and private addresses MUST be caught before any request, with the reason
  "PageSpeed cannot reach a local site".
- **FR-011**: History MUST keep a failed run with its reason and no score; anything that summarises scores MUST skip
  failed runs.
- **FR-012**: A stored run with score 50, no measures, and a summary that is a PageSpeed diagnostic message repeated as
  its only recommendation MUST be read as a failure with that message. Stored rows MUST NOT be rewritten or deleted.
- **FR-013**: The performance card MUST show "Could not run", the reason, and a Retry button for a failed run.
- **FR-014**: Operations-mode changes, refusals of CoreX screens (one event per counted entry of the access log),
  sign-in lockouts and access-request decisions MUST be written to the activity log, in the operations, access or
  security area. A lockout event MUST NOT store the typed username or the visitor's address.
- **FR-015**: The Security events card MUST show the newest five events from those three areas, read from those areas
  only.
- **FR-016**: The card MUST say from when it has records, and its empty message MUST name only what is recorded.
  Earlier events MUST NOT be copied from their own logs.

**Captcha (US-SAFE-16)**

- **FR-017**: "Test verification" MUST be placed under the secret key field the Settings form renders, and only when a
  keyed provider and both keys are saved (in the settings or in the environment).
- **FR-018**: Its answer MUST be shown in a polite live region, in words, for each answer the test route gives.
- **FR-019**: Without a provider or a key, the secret field MUST say what is needed instead.

**Add-ons (US-SAFE-08)**

- **FR-020**: Enabling MUST check WordPress's result; on failure the screen MUST show an error with WordPress's
  message, write no feature flag and queue no kit prompt.
- **FR-021**: An add-on active for the network MUST show a switch that cannot be used, with "Managed by the network
  administrator"; a must-use add-on MUST show one with "Must-use: always on". The server MUST refuse to disable either,
  and MUST never deactivate an add-on network-wide from this screen.
- **FR-022**: Each enable and disable attempt MUST be recorded in the activity log (addons area) with the person and the
  result.

**Login screen (US-SET-10)**

- **FR-023**: The login screen MUST NOT draw a single sign-on control or the "or" divider, whatever is stored.
- **FR-024**: The Brand settings MUST NOT offer the SSO switch; the stored value MUST be left as it is.

**Blog Pro (US-BLG-05)**

- **FR-025**: Without reading data for the chosen post, Blog Pro MUST show neither the reading-signals panel nor the
  period line, and MUST show one line saying CoreX does not collect reading data yet.
- **FR-026**: The Sharing card MUST be removed, and no text on the screen MUST say or imply that visits are recorded.
- **FR-027**: With reading data, the panel MUST appear as spec 075 specifies.

**Settings (US-SET-14)**

- **FR-028**: A settings save MUST write a field only when the value posted differs from the value the form showed for
  it, and MUST NOT write any field of a section whose add-on is inactive or not installed.
- **FR-029**: A checkbox unticked on the form MUST still be saved as off; the saved message and refused-field alert
  MUST work as today.
- **FR-030**: Settings already changed by the old behaviour MUST NOT be repaired automatically; the release notes MUST
  say how an owner checks the image conversion switches.

### Key Entities

- **Overview row**: a label, a state in words, a tone and, when not done, where to fix it.
- **Insight result**: a provider's run: completed with a score, grade and measures, or failed with a kind and a
  reason and no score.
- **Activity event**: the shared activity log's record (area, kind, actor, target, outcome, time).
- **Settings field write**: one field's posted value compared with the value shown.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a fresh install, no row of the Overview reads a success state (0 of the rows listed in story 1).
- **SC-002**: In every combination of protection on/off, hiding on/off and the recovery constant set/unset, "Hidden" is
  shown exactly when `/wp-login.php` does not serve the login form.
- **SC-003**: No failed PageSpeed run, stored before or after the release, is shown with a score or grade.
- **SC-004**: After 30 unrelated events, the four security events of story 4 are all still on the card.
- **SC-005**: On a site with captcha keys saved, the test button is present and answers in under the provider's
  timeout (10 seconds).
- **SC-006**: A failed activation never reads "Enabled", and no sub-site action changes another site's add-ons.
- **SC-007**: Saving any Settings tab with no edits changes 0 stored settings.

## Client impact

Owners of both client sites read different, accurate rows on Overview, Operations & Security, Insights and Blog Pro;
nothing public changes apart from the login screen, which loses an SSO button only where an owner ticked it. Muva's
captcha test button appears (reCAPTCHA v3 keys saved). Muva has no PageSpeed key, so its Insights shows "Could not
run" with the reason instead of a D. Both clients have Corex Media on, so the Settings defect did not touch their
values. Both client repositories carry `tests/Unit/Config/LoginSsoTest.php`, which arrives changed with the release.

## Assumptions

- The Security Center's login protection settings are the source of truth for whether the default login is hidden.
- The PageSpeed diagnostic messages are the ones `PsiDiagnostic` gives today; a translation of them is recognised in the
  site's current language.
- No CoreX code records reading views yet (US-BLG-04 is a later release).
- Mode changes made through any route (screen, command line, production launch) pass through the operations mode store.
