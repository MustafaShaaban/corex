# Feature Specification: A code-defined form's wording, markup and protection

**Feature Branch**: `spec/104-form-contract-and-protection`

**Created**: 2026-10-08

**Status**: Draft

**Input**: Two reports from the first client site, filed as issues #248 ("The form block cannot be
restyled or relabelled without rewriting its renderer") and #264 ("A form defined in code cannot be
protected by a captcha"), and the owner's instruction of 2026-10-08 to fix every open issue. The
site has a designed lead form that wants "Request a call" on its button and a challenge on a form
it draws itself.

## Why this spec exists

A site can define a form in code: a class that names its fields and its listeners, registered
with CoreX Forms and placed on a page with the Form block. Read at `80d74cf6`:

- **The form cannot say what it reads.** The button always says "Send". A successful submission
  always says "Thank you — your message has been sent." A failed one always says "Please review the
  highlighted fields and try again." A form's class can name its fields and nothing about its
  wording.
- **The form cannot be drawn differently.** The block prints fixed markup. A site whose design
  differs from it has to write its own renderer, and to do that it must reproduce what the
  front-end runtime expects: the form's endpoint and security token, the exported schema, the
  translated messages, an error slot for each field, a status region, and the trap field. None of
  that is published as something a site may rely on.
- **The form cannot ask to be protected.** A site that has chosen a challenge provider and saved
  its keys protects the forms built in the admin. A form defined in code is guarded by the security
  token, the rate limit and the trap field, whatever the site configured: nothing lets it ask for
  a challenge, its page gets no challenge script, and its submit route discards a token before
  anything could check it.
- **Two of the three providers challenge nobody.** Turnstile and hCaptcha can be chosen, given keys
  and tested. Nothing places their widget on any form, built in the admin or defined in code. The
  admin says so today (DECISIONS #258); it is still a provider a site can select that does nothing.

The first three share one cause: a code-defined form has no contract beyond its fields. This spec
gives it one.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A form says what it reads (Priority: P1)

A developer building a lead form wants its button to read "Request a call", its confirmation to
read "Thank you. We will call you within one working day.", and its general error to match. They
state the three in the form's definition. A form that states nothing reads exactly as it does
today.

**Why this priority**: It is the smallest thing a designed form needs, the reported site needs it
now, and every other story builds on a form being able to describe itself.

**Independent Test**: Define a form that states a button label and both messages, place it on a
page, and read the button; submit it empty and read the error; submit it valid and read the
confirmation. Do the same with the shipped contact form and see the wording it has always had.

**Acceptance Scenarios**:

1. **Given** a form that states its button reads "Request a call", **When** the form is shown,
   **Then** the button reads "Request a call".
2. **Given** a form that states its confirmation, **When** a valid submission is accepted,
   **Then** the visitor reads that confirmation.
3. **Given** a form that states its general error, **When** a submission is refused for its
   answers, **Then** the visitor reads that error beside the per-field messages.
4. **Given** a form that states none of the three, **When** it is shown and used, **Then** its
   button and both messages are the ones CoreX shows today, in the site's language.
5. **Given** a form whose stated wording contains markup, **When** the form is shown, **Then** the
   markup is displayed as text and is not interpreted.

---

### User Story 2 — A form defined in code is challenged (Priority: P1)

A site has chosen reCAPTCHA and saved its keys. Its lead form is defined in code. The developer
states in the form's definition that the form is protected. From then on a visitor's submission
carries a fresh challenge token, the server checks it before anything is stored or sent, and a
submission that fails the check is refused with a message the visitor can read. A form that does
not ask is treated as it is today.

**Why this priority**: The reported site saved its keys believing its form was protected, and it
is not. A site cannot be told "choose a provider" and then have the only form it owns ignored.

**Independent Test**: With a provider configured, mark a code-defined form as protected, load its
page and confirm the provider's script is present; submit with a token the provider accepts and
see the submission stored; submit with a token it rejects and see a refusal, nothing stored and no
email sent. Load a page with only an unprotected form and confirm no provider script is loaded.

**Acceptance Scenarios**:

1. **Given** a provider is configured and a code-defined form says it is protected, **When** its
   page is shown, **Then** the page loads what the provider needs and the form can carry a token.
2. **Given** that form, **When** a visitor submits it and the provider accepts the token, **Then**
   the submission is processed as it would have been, and the token is not kept with the answers.
3. **Given** that form, **When** a submission arrives with no token or one the provider rejects,
   **Then** it is refused, nothing is stored, no listener runs, and the response says the
   challenge failed in a way a script can tell from a validation failure.
4. **Given** a form that does not say it is protected, **When** it is submitted, **Then** no
   challenge is asked for or checked, as today.
5. **Given** a form that says it is protected on a site with no provider configured, **When** it
   is submitted, **Then** it is accepted under the protection it always had, and the page loads
   no provider script.
6. **Given** a page that holds no protected form, **When** it is shown, **Then** no provider
   script is loaded on it.
7. **Given** a form built in the admin and a form defined in code with the same name, both
   protected, **When** each is submitted, **Then** each is checked against its own action and
   neither is refused because of the other.

---

### User Story 3 — A site draws a form its own way (Priority: P2)

A developer has a design whose form does not look like the stock one: fields in a card, a label
above a custom control, the button in a different place. They write the markup themselves. CoreX
hands them the parts the form cannot work without, each by name: what the form element must carry,
the fields nobody sees, a field's stock markup when they want it, the place a field's error is
written, and the place the form's status is announced. The form they draw validates, submits,
shows errors and is protected exactly as a stock one is, and keeps working when CoreX is updated.

**Why this priority**: It is the larger half of #248. It comes after the first two because a form
drawn by hand needs the wording and the protection to exist first, and a site can ship on the
stock markup until it does.

**Independent Test**: Draw a form with markup that shares nothing with the stock form but the
published parts. Submit it empty and see each error in its place; submit it valid and see it
stored and confirmed. Compare what a visitor and a screen reader are told with the stock form.

**Acceptance Scenarios**:

1. **Given** a form that supplies its own markup built from the published parts, **When** it is
   placed with the Form block, **Then** the page shows the site's markup, not the stock form.
2. **Given** that form, **When** a visitor submits it with a required answer missing, **Then** the
   message appears in the place the site put that field's error, and the field is marked invalid
   for assistive technology.
3. **Given** that form, **When** a valid submission is accepted, **Then** the confirmation is
   announced in the status place the site put on the page.
4. **Given** that form is protected, **When** it is submitted, **Then** it is challenged as a
   stock form is, with nothing extra written by the site.
5. **Given** a site's markup that leaves out a part the form cannot work without, **When** the
   form is shown to somebody who can edit the page, **Then** they are told which part is missing;
   a visitor is not shown a form that would silently fail.
6. **Given** a form that supplies no markup of its own, **When** it is shown, **Then** it is the
   stock form, unchanged.

---

### User Story 4 — Turnstile and hCaptcha challenge a visitor (Priority: P3)

A site owner chooses Turnstile or hCaptcha in Settings and saves the keys. Protected forms, built
in the admin or defined in code, now show that provider's challenge and send its token; the server
checks it; a submission without a valid token is refused. The admin stops saying the provider
challenges nobody.

**Why this priority**: It is real and it is in #264, and no site that reported a problem uses
either provider. reCAPTCHA covers the reported need, so this follows it.

**Independent Test**: Select each provider with its public test keys, load a protected form,
confirm the provider's widget is on the page, submit and see the submission accepted; switch to
the provider's always-fail test secret and see it refused.

**Acceptance Scenarios**:

1. **Given** Turnstile is selected with keys, **When** a protected form is shown, **Then** the
   provider's challenge is placed in the form and the page loads the provider's script once,
   however many protected forms it holds.
2. **Given** hCaptcha is selected with keys, **When** a protected form is shown, **Then** the
   same holds for hCaptcha.
3. **Given** either provider, **When** a visitor completes the challenge and submits, **Then** the
   server checks the token and the submission is processed.
4. **Given** either provider, **When** a submission arrives without a token or with one the
   provider rejects, **Then** it is refused as in story 2.
5. **Given** either provider is selected with keys, **When** an administrator runs the provider
   test or reads the readiness facts, **Then** neither says the provider challenges nobody.
6. **Given** a visitor reading right-to-left or using the dark theme, **When** the challenge is
   shown, **Then** it sits in the form's flow without breaking the layout, and the form's own
   error and status places still work.

---

### Edge Cases

- A form states an empty string for its button label: the stock label is used, since a button
  with no name cannot be operated by voice or read by a screen reader.
- The provider's script fails to load or the provider is unreachable when the visitor submits: the
  visitor is told the submission could not be verified and may try again; the form is not left
  disabled.
- The provider is unreachable when the server checks a token: the submission is refused, as a
  protected flow's is today. Protection fails closed.
- A token is replayed: refused, as today for flows.
- A protected form is shown twice on one page, or beside a protected flow: each instance gets its
  own token and one does not consume the other's.
- A site changes provider while pages are cached: a submission carrying the old provider's token
  is refused and the visitor is told to reload.
- A form supplies its own markup and also states wording: the wording reaches the published parts
  (the button the site asks for, the messages the runtime shows).
- A form's own markup throws while it is being built: the block shows nothing to a visitor and
  the failure is logged, as any block renderer's failure is today.
- A submission to a protected form arrives from a script that never loaded the page (a headless
  client): it must carry a token like any other, and the published contract says where.

## Requirements *(mandatory)*

### Functional Requirements

**Wording**

- **FR-001**: A code-defined form MUST be able to state its submit label, its confirmation message
  and its general error message.
- **FR-002**: A form that states none of them MUST read exactly as the stock form reads today, in
  the site's language.
- **FR-003**: Stated wording MUST be shown as text; markup in it MUST NOT be interpreted.
- **FR-004**: An empty submit label MUST fall back to the stock label.

**Protection of a code-defined form**

- **FR-010**: A code-defined form MUST be able to state that it is protected, and MAY state the
  action name and score threshold a provider that uses them is asked to check, with the same
  defaults a form built in the admin has.
- **FR-011**: A page that shows a protected form with a provider configured MUST load what the
  provider needs. A page with no protected form MUST NOT load it.
- **FR-012**: A submission to a protected form MUST have its token checked before any answer is
  validated, stored, or handed to a listener.
- **FR-013**: A submission refused by the check MUST be answered with a refusal that names the
  challenge as the reason through a stable code distinct from a validation failure, and with a
  message a visitor can read. Nothing is stored and no listener runs.
- **FR-014**: The token MUST NOT be stored with the answers or passed to a listener.
- **FR-015**: A form that does not state it is protected MUST behave as it does today.
- **FR-016**: A protected form on a site with no provider configured MUST accept submissions under
  the protection it has today.
- **FR-017**: A protected code-defined form and a protected admin-built form MUST NOT interfere
  when they share a name.
- **FR-018**: The same check, with the same outcomes, MUST be used for both kinds of form; a
  second implementation of the verification is not acceptable.

**A site's own markup**

- **FR-020**: A code-defined form MUST be able to supply its own markup in place of the stock form.
- **FR-021**: CoreX MUST publish, as a supported contract, each part a hand-drawn form needs: what
  the form element carries, the hidden fields, a field's stock markup, a field's control
  attributes, a field's error place, the status place, and the submit button.
- **FR-022**: A form drawn from those parts MUST validate in the browser, submit, show per-field
  and general errors, confirm, and be protected, with no script written by the site.
- **FR-023**: The published contract MUST be documented with a complete worked example, and MUST
  name which parts are required.
- **FR-024**: When a required part is missing, somebody who can edit the page MUST be told which;
  a visitor MUST NOT be given a form that fails silently.
- **FR-025**: Accessibility of a hand-drawn form built from the published parts MUST match the
  stock form's: each control named, errors associated with their control and announced, status
  announced, the trap field hidden from assistive technology.
- **FR-026**: The stock form's markup, classes and behaviour MUST be unchanged for a form that
  supplies no markup.

**Turnstile and hCaptcha**

- **FR-030**: With Turnstile or hCaptcha selected and keys saved, a protected form of either kind
  MUST show that provider's challenge and submit its token.
- **FR-031**: The provider's script MUST be loaded once per page, and only on pages with a
  protected form.
- **FR-032**: A submission without a valid token MUST be refused under FR-012 to FR-014. The
  allowance that accepts an empty token for these providers (DECISIONS #258) MUST be removed in
  the same change that places the widget.
- **FR-033**: Every statement in the admin, the readiness facts, the provider test and the
  documentation that these providers challenge nobody MUST be corrected in the same change.
- **FR-034**: The challenge MUST sit in the form in left-to-right and right-to-left layouts and in
  both themes without breaking the form's layout.

**Across all four**

- **FR-040**: Every new string a visitor or an administrator reads MUST be translatable.
- **FR-041**: The site key MAY reach the browser. The secret MUST NOT.
- **FR-042**: The documentation for code-defined forms, the front-end runtime and the challenge
  add-on MUST describe what is built, and MUST stop stating that code-defined forms are covered by
  the challenge where they were not.

### Key Entities

- **Form definition**: what a site writes to define a form in code. Gains its wording, whether it
  is protected, and optionally its own markup.
- **Form parts**: the named pieces CoreX supplies to a form's markup. The public contract of
  story 3.
- **Protection declaration**: that a form is protected, with an action and a threshold. One shape
  for admin-built and code-defined forms.
- **Challenge outcome**: passed, or refused with a reason. Recorded for a flow today; for a
  code-defined form it decides acceptance and is reported in the refusal.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer changes a form's button label and both messages by stating three values
  in the form's definition, and writes no markup and no script to do it.
- **SC-002**: On a site with a provider configured, 100% of submissions to a protected
  code-defined form that lack a valid token are refused, and none of them is stored or emailed.
- **SC-003**: A page with no protected form makes no request to a challenge provider.
- **SC-004**: A developer can replace a form's markup using only the documented parts, without
  reading CoreX's source, and the resulting form passes the same behaviour checks as the stock
  form: empty submission, invalid answer, valid submission, challenge.
- **SC-005**: A hand-drawn form built from the documented example has no accessibility failure the
  stock form does not have.
- **SC-006**: With each of the three providers selected, a protected form shows a challenge and a
  tokenless submission is refused: no provider can be selected that challenges nobody.
- **SC-007**: Every site that does none of this sees no change: the shipped contact form's markup
  and wording are byte-identical before and after.

## Assumptions

Nobody was asked these; each is the reading this spec is written to, and the owner's to overrule.

- **Wording and protection are stated in the form's definition, in code.** Not in the block's
  settings in the editor. The form is defined in code and its author is a developer; a flow is the
  product for wording an editor changes.
- **A site's markup is supplied per form, by that form's definition.** Not through a site-wide
  filter and not through a template file a theme overrides. CoreX Forms fires no WordPress filters
  today and extends through its container and its registries; one seam that matches that is
  preferred to three.
- **Protection is opt-in for a code-defined form.** A site that configures a provider does not
  have every existing code-defined form start refusing submissions when it updates. A hand-drawn
  form made before this cannot carry a token, so opting every form in would break it.
- **Turnstile and hCaptcha are placed as their visible widget** (the provider decides whether a
  visitor sees a checkbox), in the form before the submit button. An invisible mode is not offered.
- **Provider calls are not made in automated tests.** Each provider's published test keys are used
  where a real check is needed, by hand; the suites stand in for the provider's answer.

## Out of Scope

- A form builder for code-defined forms, or editing their fields in the admin. That is a flow.
- Changing the stock form's design.
- The four add-ons that check a token on their own routes (bookings, careers, newsletter,
  profile). They have the same gap, a check with nothing that produces a token, and their forms
  are not Form-block forms. Recorded as open when this lands; not built here.
- New challenge providers, or score-based behaviour for Turnstile and hCaptcha.
- A no-script fallback for a form's submission.
