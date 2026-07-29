# Feature Specification: A support email that looks like CoreX

**Feature Branch**: `spec/093-support-email-template`

**Created**: 2026-07-29

**Status**: Draft

**Input**: Owner direction — *"the support mail should have the corex design, check the design handoff
and use template or design a template based on this design system."*

## Why this spec exists

### The plain-text body was already rendering wrong

Spec 087 built the support form and composed its body as `"\n"`-joined plain text, with a docblock
saying *"deliberately plain text — this goes to a person's inbox, not to a rendering surface."* That
reasoning holds for `wp_mail()`, which sends no `Content-Type` and is read as `text/plain`.

It does not hold for the other rung. `addons/corex-email/src/Driver/WpMailDriver.php` stamps
`Content-Type: text/html; charset=UTF-8` on **every** message. So on any site with Corex Mail active
— the configuration most sites will run — those newlines collapsed and the recipient got one run-on
paragraph. **The email was broken before it was unstyled.**

### One body cannot serve both transports

The rendering has to follow the transport: HTML where the driver declares HTML, plain text where it
declares nothing. That is the change. The design is what the HTML rendering is *for*.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Support mail is readable and looks like the product (Priority: P1)

**Independent Test**: send from the Guides screen on a site with Corex Mail active; the message
arrives as HTML carrying the admin's brass, with the message's own line breaks intact.

### User Story 2 — A site without Corex Mail still gets support mail (Priority: P1)

**Independent Test**: with no `Mailer` bound, the same submission sends plain text through
`wp_mail()` — and the add-on does not fatal on a missing class (Principle IX).

### Edge Cases

- **A reader typing markup.** The renderer escapes merged values, so it cannot become live. The
  message keeps its line breaks via `white-space: pre-wrap` rather than a `<br>` built from text
  that is deliberately no longer trusted.
- **A bound mailer but no registered template.** An unknown template name renders an *empty*
  message, silently. The provider passes a name only when it registered one.
- **Corex Mail absent entirely.** `SupportRequestTemplate` extends a Corex Mail class, so naming it
  would fatal on the missing parent. Guarded by `class_exists()` before the autoloader is asked.

## Requirements *(mandatory)*

- **FR-001**: The rendering MUST match the transport's declared content type.
- **FR-002**: The HTML MUST carry the CoreX admin identity.
- **FR-003**: The add-on MUST work fully without `addons/corex-email`.
- **FR-004**: The subject MUST carry the site name and the category — the two facts that make one of
  these triageable.
- **FR-005**: Nothing a reader types may become live markup.
- **FR-006**: The message's own line breaks MUST survive.
- **FR-007**: Both renderings MUST be composed from one set of parts.

## Success Criteria *(mandatory)*

- **SC-001**: A real send through Corex Mail arrives as HTML with `#ad8643` present and the subject
  reading `[Site] Guides support: <category>`.
- **SC-002**: With no mailer bound, the same message sends as plain text with its newlines.
- **SC-003**: No `style` attribute is malformed.
- **SC-004**: Every `{{ support.* }}` the template asks for is supplied by the context.

## Out of scope

- **Making this template editable in Email Studio.** Code templates and Studio templates are separate
  pipelines; bridging them is its own piece of work.
- **Changing `Layout`.** It is shared by every site's mail and correctly carries no opinion — see the
  note below about the accent it draws.

## Known, and stated rather than hidden

`Layout` draws the shell's top rule from the **brand** accent, which resolves to `theme.json`'s
`primary` — navy `#0B1F3B`. The message card inside carries the admin brass. So the email is a navy
rule around a brass-edged card.

That is not an oversight. `Layout` is shared by every site's transactional mail and injects the
*site's* colour by design; overriding it from one add-on's template would make one message lie about
the brand. If the two should match, the fix is to migrate the brass into `theme.json` — which the
design handoff calls the approved direction and has not happened — not to special-case it here.
