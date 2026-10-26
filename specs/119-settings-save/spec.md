# Feature Specification: Settings that save what you changed

**Feature Branch**: `spec/119-settings-save`

**Created**: 2026-10-23

**Status**: Draft

**Input**: The CoreX revamp's release R3.4, "Settings that save what you changed". Its nine stories are this spec's
acceptance criteria: US-SET-01, US-SET-02, US-SET-03, US-SET-06, US-SET-07, US-SET-08, US-SET-09, US-SET-13 and
US-SET-15 (the last one added by the accepted change request CR-019). The stories, their 47 test cases and the reading
of the code behind them are kept in the owner's private planning workspace. Spec 114 (R0.3) already made a save write
only the fields whose value changed and skip a section whose add-on is off (US-SET-14); this spec builds on it.

## Why this spec exists

The Settings screen is one long form of nine tabs with one button at the bottom. Nothing says an edit is unsaved,
nothing restores what was there, and leaving the page loses an edit silently. A value set in the site's `.env` file
shows as an empty editable field whose saves do nothing, and the captcha section reports keys from `.env` as missing.
Provider sections say "Not set" and never what a missing key stops, and a failed test leaves no trace. A saved secret
cannot be taken off the site without database access. The Media library can be converted to WebP only from a command
line, which shared hosts do not have. A section for an add-on that is off gives no way to it, the Guides section has no
add-on state at all, most labels cannot be translated, on/off settings are bare checkboxes, and the login screen's
logo link can be set only in code.

## Clarifications

### Session 2026-10-23 (decided under the owner's 2026-10-21 mandate)

The owner delegated the revamp and asked not to be consulted; where a story leaves a choice open, the planning
workspace's recommended answer is taken, and where none exists the choice below is recorded in `DECISIONS.md`.

- Q: The story groups the sections "as the design groups them", but the design's groups include screens that are not
  Settings (Operations & Security) or not built yet (Data retention), and two add-on sections must have addresses of
  their own (a not-installed add-on's section leaves the navigation). Which sections share a page? → A: **Seven
  pages**: General and appearance (Brand and the Dashboard widgets), Email (Mail and Forms), Captcha, Media,
  Insights, Guides and Advanced. Each add-on's section is a page of its own. Data retention joins when its release
  (R3.5) builds it.
- Q: Where is the sub-navigation? → A: **Nested under Settings in the CoreX rail**, as the design draws it. On a narrow
  screen, where the rail becomes a row of links, the Settings pages are a row of their own under it.
- Q: The story asks the Insights section to say "performance checks cannot run" without a PageSpeed key. CoreX runs
  PageSpeed without a key on Google's shared quota (the Overview says "Keyless (shared quota)"). → A: **Say what is
  true**: without a key, performance checks share Google's keyless quota and are refused when it runs out. Saying they
  cannot run would be untrue on a day the quota is free.
- Q: What does the Cloudflare "Test connection" check? → A: **That Cloudflare accepts the token** (Cloudflare's token
  verification). Whether the account ID is right is checked when a scan runs; the section says so.
- Q: What does the PageSpeed "Test connection" check? → A: **That Google accepts the key**, with a request that runs no
  measurement (a measurement takes up to a minute).
- Q: When is a remembered test result out of date? → A: **When any value the test used changes**, wherever it is set
  (the screen or `.env`). The result keeps a one-way fingerprint of those values, never the values.
- Q: How does Remove work without scripts? → A: **It is a box to tick, "Remove the saved key when I save"**, with
  what stops working beside it. With scripts it is a "Remove" button that asks for confirmation and then ticks it.
- Q: The Add-ons card has no "needs setup" state yet (US-ADD-02 is release R3.6). → A: **The captcha card gains one
  line** saying a key is missing and linking to Settings > Captcha, drawn from the same answer Settings uses. R3.6
  turns it into its badge.
- Q: Which add-on settings follow their add-on? → A: **Media, Captcha and Guides.** Insights, Mail, Forms and the
  Dashboard widgets belong to plugins that are always on.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Each Settings section saves on its own, with a save bar that knows about unsaved changes (US-SET-01, Priority: P1)

A site owner changing settings sees when an edit is unsaved, can put it back, is warned before losing it, and a save
changes only that section.

**Why this priority**: every other story on this screen is saved through it.

**Independent Test**: open Settings > General and appearance, change the tagline, use Discard, change it again, try to
leave, then save; turn scripts off and save again.

**Acceptance Scenarios**:

1. **Given** Settings > General and appearance, **When** the tagline is changed, **Then** the bar reads "Unsaved
   changes" with Discard and Save enabled; before any change it reads "No changes" with both disabled.
2. **Given** an unsaved change, **When** Discard is pressed, **Then** every field returns to its saved value without a
   reload and the bar reads "No changes".
3. **Given** an unsaved change, **When** another Settings page is chosen or the page is reloaded, **Then** the browser
   asks before leaving; cancelling keeps the edit. Nothing asks when there are no changes.
4. **Given** Corex Media off with its switches on, **When** General and appearance is saved and Media turned on,
   **Then** the switches are still on.
5. **Given** "sales@" typed as the contact email, **When** the page is saved, **Then** the message names Contact email
   and "sales@", the other fields are saved, and the bar still reads "Unsaved changes" with "sales@" in the field.
6. **Given** a page just saved, **When** the browser reloads, **Then** it does not offer to resend the form and the
   page shows that section.
7. **Given** scripts are off, **When** a section is edited and saved, **Then** it saves with its own button and shows
   the result.
8. **Given** any page, **Then** it has its own address, and the sub-navigation marks it as the current page.

---

### User Story 2 - A setting fixed in .env or by the network shows its real value and cannot be edited (US-SET-02, Priority: P1)

A developer or owner sees the value in force and where it is set, and never types into a field whose saves do nothing.

**Independent Test**: put `BRAND_COMPANY_NAME=Acme` in the site's `.env`, open General and appearance, save another
field, then remove the key.

**Acceptance Scenarios**:

1. **Given** `.env` holds `BRAND_COMPANY_NAME=Acme` and the option holds "Old", **When** the page opens, **Then** the
   field shows "Acme", cannot be edited, and says "Set in the site's .env file".
2. **Given** that, **When** another field is changed and saved, **Then** the option still holds "Old" and the result
   does not mention the locked field.
3. **Given** `.env` holds the captcha secret and site key with reCAPTCHA chosen, **When** Captcha opens, **Then** no
   key is reported missing, the secret shows "Set in .env" and its value is nowhere in the page.
4. **Given** a multisite whose code locks `brand.tagline` with the network value "Net", **When** General and appearance
   opens on a sub-site, **Then** it shows "Net", cannot be edited, and says "Locked by the network administrator".
5. **Given** the key is removed from `.env`, **When** the page reopens, **Then** the field is editable and shows "Old".
6. **Given** a field with no `.env` value and no lock, **Then** it behaves as today.

---

### User Story 3 - Provider settings say what is missing and when a test failed (US-SET-03, Priority: P2)

An owner setting up captcha or Insights keys reads which key is missing, what that stops, and whether the last test
failed.

**Independent Test**: choose reCAPTCHA with only a site key; save a wrong secret and test; set only a Cloudflare
token; test a PageSpeed key.

**Acceptance Scenarios**:

1. **Given** reCAPTCHA with a site key and no secret, **When** Captcha opens, **Then** it reads "The secret key is
   missing, so forms are protected by the honeypot only".
2. **Given** a wrong secret saved, **When** "Test verification" is pressed and the page reloaded, **Then** the answer
   was announced, and the page reads "Last test failed on <today>: the provider refused the secret key".
3. **Given** that, **When** a new secret is saved, **Then** the failed-test line is gone and it reads "Not tested yet".
4. **Given** a Cloudflare token and no account ID, **When** Insights opens, **Then** it names the missing account ID.
5. **Given** a valid PageSpeed key, **When** "Test connection" is pressed, **Then** it reads "Last tested on <today>:
   working", and the key is in no response and nowhere in the page.
6. **Given** Muva's settings (reCAPTCHA v3, both keys saved), **When** Captcha opens, **Then** no key is reported
   missing and the test is offered.
7. **Given** no PageSpeed key, **When** Insights opens, **Then** it says performance checks share Google's keyless
   quota and are refused when it runs out.

---

### User Story 4 - The Media library can be converted to WebP from the admin (US-SET-06, Priority: P2)

An owner on shared hosting converts images uploaded before WebP was on, from Settings, and watches it finish.

**Independent Test**: upload JPEGs with conversion off, turn it on, start "Convert existing library", close and reopen
the page, cancel a second run.

**Acceptance Scenarios**:

1. **Given** JPEG uploads without WebP copies and conversion on, **When** "Convert existing library" is started,
   **Then** the number of images to check shows, progress (converted, skipped, failed, of the total) advances, and the
   end shows the three counts and when it ended.
2. **Given** a run in progress, **When** the page is closed and reopened, **Then** the progress shown is correct and
   still advancing.
3. **Given** a run in progress, **When** it is started again, **Then** it says a conversion is already running.
4. **Given** a run in progress, **When** it is cancelled, **Then** it stops and reads "Cancelled" with its counts and
   time; originals are untouched.
5. **Given** conversion switched off, or a server that cannot write WebP, **When** Media opens, **Then** the control is
   unavailable and says which.
6. **Given** an upload that cannot be converted, **When** the run ends, **Then** the file is listed as failed with its
   reason, and the run is in the activity log.
7. **Given** Corex Media inactive, **Then** the control is not offered.

---

### User Story 5 - A section for an add-on that is off says so and links to Add-ons (US-SET-07, Priority: P2)

An owner sees that a section belongs to an add-on that is off and goes to it; a section for an add-on that is not
installed does not appear.

**Independent Test**: turn Corex Guides off and open Settings > Guides; remove the captcha add-on's folder and open
Settings and the Captcha address.

**Acceptance Scenarios**:

1. **Given** Corex Guides off, **When** Settings > Guides opens, **Then** it reads "Corex Guides is off. These settings
   apply when it is on." with "Open Add-ons", and its fields are read-only.
2. **Given** that, **When** "Open Add-ons" is pressed, **Then** the Add-ons screen opens at the Guides card.
3. **Given** the captcha add-on not installed, **When** Settings opens, **Then** Captcha is not in the navigation, and
   its address shows the standard "not available" state.
4. **Given** Corex Media off with its switches on, **When** another section is saved, **Then** the switches are
   unchanged.
5. **Given** Arabic, **When** a section of an add-on that is off opens, **Then** the notice and the add-on's name are
   translated.

---

### User Story 6 - Every word on the Settings screen can be translated (US-SET-08, Priority: P1)

An owner whose admin is in Arabic reads every title, label, help line and choice in it.

**Independent Test**: load a test catalogue that marks every translated string and read every Settings page.

**Acceptance Scenarios**:

1. **Given** the release, **Then** every Settings title, label, help line, link text, choice and notice is in CoreX's
   translation catalogue.
2. **Given** a test catalogue and a right-to-left admin, **When** every page opens, **Then** no untranslated text
   remains except brand and product names and values, and the page is right-to-left.
3. **Given** right-to-left, **When** a line holds an address or a number, **Then** the value keeps its own direction.
4. **Given** a Settings label added without translation, **When** the automated checks run, **Then** they fail.

---

### User Story 7 - On/off settings are switches (US-SET-09, Priority: P3)

An owner reads every on/off setting at a glance, the same way as on the Add-ons screen.

**Acceptance Scenarios**:

1. **Given** any page with an on/off setting, **Then** it is a switch with a visible On or Off; no checkbox remains.
2. **Given** a keyboard, **When** "Enable live delivery" is reached and Space pressed, **Then** it toggles, and its name
   and state are announced.
3. **Given** "Attention widget" switched on and saved, **Then** the stored value is "1", as the checkbox wrote.
4. **Given** right-to-left, **When** a switch is toggled, **Then** the knob sits at the start edge for Off and the end
   edge for On; the state never depends on colour alone.
5. **Given** a section whose add-on is off, **Then** its switches are read-only.

---

### User Story 8 - Brand settings choose where the login screen's logo links (US-SET-13, Priority: P3)

An owner points the login screen's logo at the company's site.

**Acceptance Scenarios**:

1. **Given** the field blank, **When** the login screen's logo is clicked, **Then** it goes to the site's home page,
   and the field says so.
2. **Given** `https://example.org/` saved, **When** the logo is clicked, **Then** it goes there.
3. **Given** "example" typed, **When** saved, **Then** it is refused with the usual message and the old value kept.
4. **Given** `BRAND_LOGIN_URL` in `.env`, **When** General and appearance opens, **Then** the field is locked and
   shows the value.

---

### User Story 9 - A saved provider secret can be removed from Settings (US-SET-15, Priority: P2)

An owner whose captcha secret, PageSpeed key or Cloudflare token has leaked takes it off the site from Settings.

**Acceptance Scenarios**:

1. **Given** a saved Turnstile secret, **When** Captcha opens, **Then** it says a value is saved, offers Remove, and
   the value is nowhere in the page.
2. **Given** that, **When** Remove is confirmed and the page saved, **Then** the stored secret is deleted, the field
   says no value is saved, and Settings and the Add-ons card show captcha as needing setup.
3. **Given** a saved secret, **When** the page is saved with the field empty, **Then** the value is kept.
4. **Given** the secret set in `.env`, **Then** there is no Remove and the field says where it is set.
5. **Given** a removal, **Then** the activity log records it, without the value.
6. **Given** a person who may not change Settings, **When** the save is posted with a removal, **Then** it is refused
   and nothing is removed.

---

### Edge Cases

- A refused value and a removal on the same save: the removal happens, the refused field is named, nothing else on
  another page changes.
- A save posted for a page whose add-on is off, or for a page that does not exist, writes nothing.
- A locked secret posted with a value (a crafted request) is not written.
- Turning conversion off while a library conversion runs: the run stops at its next step and says why.
- A run whose handler disappears (Corex Media deactivated mid-run) fails with its reason; nothing is lost.
- A test result recorded for values that changed since reads "Not tested yet".
- The Advanced page has nothing to save, so it has no save bar.

## Requirements *(mandatory)*

### Functional Requirements

**Pages and the save bar (US-SET-01)**

- **FR-001**: Settings MUST be split into pages, each with its own address, reached from a sub-navigation under
  Settings in the CoreX rail that marks the current page.
- **FR-002**: Each page MUST have a bar fixed to the bottom that reads "No changes", "Unsaved changes" or "All changes
  saved", with Discard and Save enabled only when there are unsaved changes.
- **FR-003**: A save MUST write only fields of the page it was posted from, and only what changed (spec 114, FR-028);
  a page whose add-on is off or absent MUST NOT be written.
- **FR-004**: Discard MUST restore every field of the page to its saved value without a reload.
- **FR-005**: Leaving a page with unsaved changes MUST ask for confirmation; leaving without changes MUST NOT.
- **FR-006**: After a save the browser MUST be redirected to the page with its result, so reloading does not resend.
- **FR-007**: A refused value MUST be named as today, and the page MUST show it in its field and read "Unsaved changes".
- **FR-008**: Without scripts, each page MUST save with its own button and show the result.

**Locked values (US-SET-02)**

- **FR-009**: A field whose key is set in the site's `.env` MUST show the value in force (a secret shows "Set in .env"
  and never its value), be read-only, and say "Set in the site's .env file".
- **FR-010**: A field locked by the network MUST show the network's value, be read-only, and say "Locked by the network
  administrator".
- **FR-011**: A save MUST NOT write a locked field, and MUST NOT report it as saved or refused.
- **FR-012**: A section's states (configured, needs setup) MUST be worked out from the value in force.

**Provider states (US-SET-03)**

- **FR-013**: With a key-based captcha provider and a key missing, the section MUST name the missing key and its
  effect: a missing secret leaves forms protected by the honeypot only; a missing site key stops the provider's check
  from loading on forms.
- **FR-014**: Without a PageSpeed key, Insights MUST say performance checks share Google's keyless quota; with only one
  of the Cloudflare token and account ID, it MUST name the other.
- **FR-015**: With its keys present, each provider MUST have a test (captcha "Test verification"; PageSpeed and
  Cloudflare "Test connection") whose answer is shown in words in a live region.
- **FR-016**: The latest test MUST be remembered: "Last test failed on <date>: <reason>" or "Last tested on <date>:
  working"; without a test, or after any value it used changed, "Not tested yet".
- **FR-017**: No key value MUST be shown, stored with a result, or sent to the browser.

**Media library conversion (US-SET-06)**

- **FR-018**: Settings > Media MUST offer "Convert existing library", saying originals are kept and images with a WebP
  copy are skipped.
- **FR-019**: Starting it MUST show the number of images to check, then converted, skipped and failed of the total,
  updating while the page is open and correct when it is reopened.
- **FR-020**: It MUST be cancellable; a cancelled or finished run MUST say so with its counts and end time.
- **FR-021**: Only one run MAY exist at a time; starting another MUST say one is running.
- **FR-022**: With conversion off or no WebP support on the server, the control MUST be unavailable and say which.
- **FR-023**: Failures MUST be listed by file name with the reason; the run MUST be recorded in the activity log.

**Add-on sections (US-SET-07)**

- **FR-024**: Media, Captcha and Guides MUST follow their add-on's state.
- **FR-025**: Not installed: the page MUST NOT be in the navigation, and its address MUST show the standard "not
  available" state.
- **FR-026**: Installed and off: the page MUST say "<Add-on> is off. These settings apply when it is on.", link "Open
  Add-ons" to that add-on's card, and show its fields read-only.
- **FR-027**: The add-on's name in the notice MUST be translatable.

**Translation (US-SET-08)**

- **FR-028**: Every title, label, help line, link text, choice, notice and button on Settings MUST be translatable in
  the `corex` text domain; brand and product names MAY stay as they are.
- **FR-029**: Values inside a translated line (an address, a number) MUST keep their own direction.
- **FR-030**: An automated check MUST fail when a Settings title, label, help line or choice is not translatable.

**Switches (US-SET-09)**

- **FR-031**: Every on/off setting MUST be a switch with a visible On or Off, named by its label, operable with Space,
  announced with its state, saved as today ("1" or "0").
- **FR-032**: In right-to-left the switch MUST mirror; its state MUST NOT depend on colour alone.

**Login logo link (US-SET-13)**

- **FR-033**: General and appearance MUST have "Login logo link"; blank keeps the site's home page and says so; a web
  address is where the login screen's logo links; anything else is refused with the usual message.

**Secret removal (US-SET-15)**

- **FR-034**: A secret field with a saved value MUST say a value is saved and offer Remove, which asks for confirmation
  naming what stops working; the save then deletes the stored secret.
- **FR-035**: An empty secret field MUST keep the saved value; a secret set in `.env` MUST NOT offer Remove.
- **FR-036**: After a removal the provider MUST read as needing setup on Settings and, for captcha, on its Add-ons card.
- **FR-037**: A removal MUST be recorded in the activity log without the value; only a person who may change Settings
  may remove.

### Key Entities

- **Settings page**: an address, a translated name, the sections it saves, and the add-on it belongs to, if any.
- **Field state**: what a field shows, the stored value to restore, and where its value is locked, if anywhere.
- **Setting lock**: where a value in force comes from (`.env` or the network) and the value.
- **Provider test record**: per provider, when it was tested, whether it worked, the reason in words and a fingerprint
  of the values it used.
- **Library conversion run**: a background job with its total, converted, failed and skipped counts, state, end time
  and the failures by file name.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Saving any page changes 0 stored settings outside that page.
- **SC-002**: With a key in `.env`, 0 editable fields show a value other than the one in force.
- **SC-003**: No key value appears in any Settings page source or test response (checked for every secret field).
- **SC-004**: A library of 40 images is converted from the admin with no command line, and the counts shown add up to
  the total.
- **SC-005**: With a marking test catalogue, 0 unmarked words remain on any Settings page except brand names and values.
- **SC-006**: Every on/off setting is a switch; the stored values before and after the release are byte-identical.
- **SC-007**: A leaked secret is removed in one confirmed save, with one activity record.

## Client impact

Muva, Perego and Mizzey: their owners find the Settings sections as pages under Settings in the CoreX rail, with a save
bar, instead of nine tabs; saved values are unchanged and nothing is written by the update. A value their host's `.env`
sets shows as locked (their `.env.example` files hold only `APP_NAME` and `APP_ENV`, neither a Settings field). A saved
secret gains Remove; nothing is removed until an owner presses it and saves. Muva and Perego have Guides off, so their
Guides page becomes read-only with a link to Add-ons. Both have Corex Media on and gain "Convert existing library";
nothing runs until an owner starts it. The client repositories carry copies of CoreX's unit tests
(`SettingsTabsTest.php`), which arrive changed with the release.

## Assumptions

- `.env` is read from the project root, as the config engine reads it; a dot key maps to its upper-case name.
- A network lock exists only where code registers one through `corex_setting_scopes` (spec 100).
- The bounded jobs runner (spec 068) runs the conversion: advanced by the open page, and by WP-Cron when it is closed.
- Captcha "Test verification" stays the captcha add-on's route (spec 044, spec 114); it reports its result to Settings.
- CoreX ships no catalogue yet; translation is proven with a test catalogue that marks every string.
