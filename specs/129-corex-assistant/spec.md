# Feature Specification: CoreX Assistant, a site chat that answers from the site and hands off to the team

**Feature Branch**: `spec/129-corex-assistant`

**Created**: 2026-11-09

**Status**: Specified, planned and built in one pass on the owner's decision relayed from the Perego session
(2026-11-09): "the assistant should be a reusable CoreX feature, not Perego-only". The behaviour below was
designed and approved with the owner for Perego (Perego spec 058, released to peregoads.com on 2026-11-09,
switched off until keys are added). This spec makes it generic. DECISIONS #600 to #608 (slice 1) and #609
to #616 (slice 2, Perego's revision 2, amended 2026-11-09).

**Input**: Perego's spec 058 and its code (`sites/perego/perego-site/src/Assistant/`, the `assistant` block,
the callback form and its tests), read on 2026-11-09, and the owner's approved design: honest labelling, no
model vendor names shown to visitors, free model tiers chained with fallback, and an in-chat hand-off to the
team.

## Why this spec exists

A client site wants a support-style chat that answers visitors from the site's own content, costs nothing to
run, says honestly what it is, and passes a visitor to a person when it cannot help. Perego built one inside its
client plugin because a client session may not edit CoreX. Every other CoreX client would have to copy it. As an
add-on it is built once, kept by the framework's tests and guards, and switched on per site.

## What is already true (checked on 2026-11-09)

- **The forms engine can carry the hand-off.** A code-defined `Corex\Forms\Form` gets the honeypot, the
  sanitising, the throttle, storage in the Submissions inbox and the team's notification
  (`SendEmailListener`), which goes to `forms.email.recipient` (else the admin email) and sets Reply-To only
  when the visitor gave a valid email. A form with no email field is still sent to the team.
- **Settings already know write-only keys.** A `password` field on the Settings screen is never drawn back,
  is kept when submitted empty, can be removed with a confirmation, and is locked (and shown as set) when the
  site's `.env` defines it: `assistant.gemini.key` is `ASSISTANT_GEMINI_KEY` there (`DotenvSource`).
- **Settings values are saved autoloaded.** `SettingsStore::save()` calls `update_option()` with no autoload
  argument, so a saved secret (the captcha secret, the PageSpeed key) is read into memory on every request.
- **Routes declare middleware.** A public CoreX route runs `nonce` and `throttle` through the `Pipeline`, and
  `PublicSubmission::MARKER` closes it while the site is in Maintenance (spec 112).
- **`ClientAddress`** gives the visitor's address, reading the proxies the site trusts.
- **CoreX has no language service and no Arabic catalogue.** Its strings are English in the `corex` domain; a
  site's language plugin sets the locale WordPress reports (`determine_locale()`), and `is_rtl()` follows it.
- **Perego's security review** (2026-11-09) fixed four things that this spec keeps: a refused request rests no
  service; one question at a time per visitor through an atomic lock; an IPv6 visitor counts by its /64; a body
  over 16 KB is refused unread, and only `user` and `assistant` roles are taken.

## User Scenarios & Testing

### User Story 1 - A visitor asks and gets an answer from the site (Priority: P1)

A visitor on any public page sees a round button in the bottom inline-end corner. Opening it loads the chat. They
type a question in their language and get a short answer drawn from the site's own content, with up to two links
to the site's pages shown as chips under it.

**Why this priority**: it is the feature.

**Independent Test**: with one model key saved and the assistant on, ask a question on a page and read the reply
and its link chips.

**Acceptance Scenarios**:

1. **Given** the assistant is on and one service has a key, **When** a public page loads, **Then** the button is
   there and no chat script or chat stylesheet has been requested.
2. **Given** the button, **When** the visitor opens it, **Then** the chat loads, focus moves to the question box,
   and the welcome, three suggested questions, the honesty note and "talk to someone" are shown.
3. **Given** a question, **When** the service answers with a known page of the site, **Then** the reply shows
   that page as a chip; an address of another site, or a made-up page of this one, is removed.
4. **Given** an Arabic page (`is_rtl()`), **Then** the chat is right to left and the button sits on the left.
5. **Given** the visitor moves to another page in the same tab, **Then** the conversation is still there; "New
   chat" clears it. Nothing of it is kept on the server.
6. **Given** a keyboard user, **Then** every control is reachable, Escape closes the panel and focus returns to the
   button; new replies are announced politely; with reduced motion nothing slides or bounces.

### User Story 2 - The chat hands the visitor to the team (Priority: P1)

When the visitor asks for a person, a price or a call, or the assistant cannot answer, a short form opens inside
the chat. Its request reaches the team like any CoreX form.

**Independent Test**: press "talk to someone", send the form, and read the submission in the Submissions inbox
and the team's email.

**Acceptance Scenarios**:

1. **Given** a reply the model marked for the team, or the visitor pressing "talk to someone", **Then** the
   hand-off form opens once, under the conversation.
2. **Given** the form (name, phone with country code, optional email, call / WhatsApp / email, best time,
   "attach this chat" on by default), **When** it is sent, **Then** it is stored as a submission of the form
   `corex-assistant-callback` and the team is notified, with or without an email; the visitor sees the
   confirmation in the chat and gets no email.
3. **Given** a visitor whose browser clock is in a known timezone, **Then** the country code is preset to that
   country; otherwise to the site's default country.
4. **Given** "by email" chosen with no email, or a missing name or phone, **Then** nothing is sent and the field
   says what is missing.
5. **Given** corex-forms is not active, **Then** the chat works and offers no form.

### User Story 3 - The site owner switches it on and keys it (Priority: P1)

The owner opens Settings → Assistant, turns it on, pastes a free key for at least one service and saves. "Check
services" shows which keyed services answer.

**Acceptance Scenarios**:

1. **Given** a fresh site, **Then** no button is shown anywhere: the assistant is off by default, and stays hidden
   while on with no key.
2. **Given** a saved key, **Then** it is never drawn back; the field says a key is saved and can be removed.
3. **Given** `ASSISTANT_GROQ_KEY` in the site's `.env`, **Then** that key is used and its field is locked.
4. **Given** keys for several services, **When** "Check services" runs, **Then** each answers "answered" or why
   it did not, and a service that answered is no longer resting.
5. **Given** the page, **Then** each service shows Ready, Resting until a time, or No key, and today's question
   count against the site-wide daily cap.
6. **Given** a saved key, **Then** its option is not autoloaded.

### User Story 4 - The chain survives spent free tiers (Priority: P1)

Free tiers run out. The assistant asks the services in order and moves on.

**Acceptance Scenarios**:

1. **Given** the first service answers 429, **Then** the next one answers the same question, and the first is
   skipped until the time it gave, else one minute; a daily limit rests it until the next UTC midnight.
2. **Given** a key or model error (401, 402, 404, a 403 about the key), **Then** that service rests an hour; a
   server error or timeout rests it a minute.
3. **Given** a refused message (400, 413, 422, a 403 that names moderation), **Then** no service rests and the
   next one is tried.
4. **Given** every service resting or failing, **Then** the chat says it cannot answer now and opens the hand-off;
   the whole search stops after 22 seconds, each call after 10.

### User Story 5 - Abuse costs the abuser, not the site (Priority: P1)

**Acceptance Scenarios**:

1. **Given** one visitor, **Then** they may ask one question at a time, 20 an hour and 60 a day; past that the chat
   answers "busy" with the hand-off, not an error.
2. **Given** the whole site, **Then** it answers at most the daily cap (600 unless set) a day.
3. **Given** a visitor on IPv6, **Then** every address in their /64 counts as one visitor; the address itself is
   never stored, only a salted hash.
4. **Given** a body over 16 KB, a role other than `user` or `assistant`, a question over 600 characters or more
   than 12 messages, **Then** the body is refused unread, the role dropped, the question cut, the oldest messages
   dropped.
5. **Given** a request without the page's REST token, **Then** it is refused (403) and the chat asks the visitor to
   refresh.
6. **Given** the site in Maintenance, **Then** the route is closed like the forms routes.

### User Story 6 - A client plugs in its knowledge and its voice (Priority: P2)

A developer gives the assistant better knowledge than the default, its own name in each language, its strings
and its mark, without editing CoreX.

**Acceptance Scenarios**:

1. **Given** no knowledge provider, **Then** the assistant knows the site's name, tagline and its published pages
   (title, address and the start of their text), capped in size.
2. **Given** a provider returned by the `corex_assistant_knowledge_provider` filter, **Then** its `Knowledge` is
   used for the visitor's language, and only the pages it names become link chips.
3. **Given** the `corex_assistant_strings` filter, **Then** every visitor-facing string can be replaced per
   language; the assistant's name from Settings is used where the filter does not replace it.
4. **Given** a mark chosen in Settings, **Then** it is the button's and the chat's image; else a built-in icon.
5. **Given** the `corex_assistant_phone_countries` filter, **Then** the site chooses the default country and the
   countries listed first.

### Edge Cases

- A visitor has the chat open for hours: the REST token may expire (12 to 24 hours); the chat says to refresh.
- The page is served from a page cache: the token in the markup may be older than the cache's lifetime. A site
  whose cache keeps pages longer than 12 hours should exclude the token or shorten the cache (documented).
- The model writes Markdown, headings or a bare address: the reply is cleaned, addresses become chips or vanish.
- The model writes the hand-off token in a different case: it is still read.
- A provider returns 200 with an empty answer: treated as a server failure for that provider.
- Private browsing with no session storage: the chat works and does not follow the visitor between pages.
- The lock of a visitor whose request died is older than 45 seconds: the next question takes it over.

## Requirements

### Functional Requirements

- **FR-001**: The add-on MUST be `addons/corex-assistant` (plugin `corex-assistant/corex-assistant.php`,
  namespace `Corex\Assistant`), listed in the add-on catalogue and the add-on provider registry, inactive until
  activated like every add-on.
- **FR-002**: The button MUST be printed on public front-end pages only (not admin, feeds, embeds, the Customizer
  preview), only when `assistant.enabled` is on AND at least one service has a key.
- **FR-003**: The button's script and stylesheet MUST be the only assistant assets on a page that was not
  interacted with; the chat code and its stylesheet MUST load on first open, or on hover or focus of the button.
- **FR-004**: The model chain MUST be Gemini, Groq, OpenRouter, Mistral, in that order, over the OpenAI chat
  completions format; a service with no key is skipped; each has a default model and an optional model setting.
- **FR-005**: Cooldowns per service as User Story 4; kept in one option, not autoloaded.
- **FR-006**: Keys and models MUST be Settings fields (`assistant.<service>.key` as `password`,
  `assistant.<service>.model` as `text`), readable from `.env` through the Config engine; password-typed Settings
  values MUST be saved with autoload off.
- **FR-007**: The endpoint MUST be `POST corex/v1/assistant`, taking `{locale, messages}` and answering
  `{reply, team, links, busy}`; it MUST run the `nonce` middleware and the assistant's limits middleware, and carry
  `PublicSubmission::MARKER`.
- **FR-008**: Limits as User Story 5; the cap is `assistant.daily_cap` (default 600).
- **FR-009**: The system message MUST tell the model to read the visitor generously, answer only from the
  knowledge, invent no prices or promises, recommend a service when the visitor states a goal, answer in the
  language of the visitor's last message (or the one they asked for), with the site's own voice rules from the
  `corex_assistant_voice` filter, stay short and plain, link only to addresses in the knowledge, refuse
  off-topic requests and role changes, never claim to be human, and end with `[[TEAM]]` when the team should be
  offered. It MUST NOT name the model or its maker.
- **FR-010**: Replies MUST be shaped server side: the team token read and removed, same-site addresses the
  knowledge names turned into at most two chips, every other web address, email address and international phone
  number removed, Markdown undone.
- **FR-011**: The hand-off MUST be the code-defined form `corex-assistant-callback` (name, phone, email,
  channel, time, transcript, page), with the forms engine's default listeners, which a site may replace with the
  `corex_assistant_callback_listeners` filter. It MUST be registered only when corex-forms is active.
- **FR-012**: Knowledge MUST come from a `KnowledgeProvider` (`for(string $locale): Knowledge`); the default reads
  the site; the `corex_assistant_knowledge_provider` filter replaces it. Default knowledge MUST be cached per
  language for six hours and dropped when a page is saved.
- **FR-013**: Every visitor-facing string MUST be translatable (`corex` domain) and replaceable per language by the
  `corex_assistant_strings` filter; no string shown to a visitor may name a model or its maker.
- **FR-014**: The Settings page "Assistant" MUST show the switch, the assistant's name and mark, the four services'
  keys and models, the daily cap, each service's status, today's count, and "Check services" (administrators only:
  `POST corex/v1/assistant/check` behind `manage_options` and the `auth` and `nonce` middleware).
- **FR-015**: The front end MUST use theme tokens only, logical properties, `prefers-reduced-motion`, and meet
  WCAG 2.2 AA (contrast from the theme's tokens, 24 px minimum targets, visible focus, names on every control).

### Key Entities

- **Provider**: id, label, base address, model, key, extra request fields.
- **Failure**: kind (limit, daily, config, rejected, server), status, retry-after, detail; how long to rest.
- **Knowledge**: locale, site name, text lines, pages (label, address); the link list the shaper reads.

## Success Criteria

- **SC-001**: A page with the assistant off or unkeyed carries no assistant markup or asset.
- **SC-002**: A page with it on carries only the button's script and stylesheet until the button is used.
- **SC-003**: The Pest and Jest suites for the add-on pass, and wp-guard, clean-code-guard, test-guard and
  docs-guard run clean on the diff.
- **SC-004**: A site with the behaviour of Perego's first release of spec 058 can drop its own copy and keep that
  behaviour by plugging in its knowledge, strings, voice, mark, country order and callback email. Perego's
  revision 2 needs slice 2 (below) (docs: `docs-app/src/content/docs/guides/assistant.md`, "Switching from a site's own copy").

## Slice 2: Perego's revision 2, made generic (2026-11-09)

Perego's assistant gained a revision 2 on 2026-11-09 while slice 1 was built, read on 2026-11-09 from
`feature/058-perego-assistant` (`36950af8`, `0bbc9a28`) and the "Revision 2" section of Perego's spec 058. Slice 1
took its generic fixes (the reply's language from the visitor's words, reading visitors generously, recommending
from a goal, the colon before a removed address, a service's rest ending when it answers). Slice 2 takes the rest,
made generic: what was Perego's (Cairo hours, a Saturday-first week, its own screen in the client plugin, its
`perego_required_for` rule) becomes CoreX settings, a forms-engine rule and a forms-engine hook. DECISIONS #609 to
#616.

### What is already true for slice 2 (checked on 2026-11-09)

- **A Settings field is one value.** The Settings registry has text, email, url, tel, media, select, checkbox,
  password, hex-colour and info fields; nothing holds an order, and a number is a text field the reader checks.
- **A Settings field shows its stored value or nothing.** Only the Media switches show a default before the first
  save (`SettingsFieldValues::MEDIA_DEFAULTS`); a switch with nothing stored shows off.
- **A row can follow a select.** `show_for` hides a row unless a select holds one of its values; the page's script
  reads `control.value`, which is `1` for a checkbox whether ticked or not.
- **A code-defined form cannot change a valid answer.** The forms engine validates, stores uploads and dispatches
  `FormSubmittedEvent` with the validated values; nothing between turns a raw value into what the inbox and the
  team's email should read.
- **The forms engine has no conditional `required`.** Its rules are required, email, max, min, max_length,
  min_length, numeric, url, phone, mime, max_size and pattern; `RuleForAbsentValue` lets a rule run on a field the
  request left out.
- **The Messages API** (`POST https://api.anthropic.com/v1/messages`, version `2026-10-21`) takes the instructions
  as `system` apart from `messages`, refuses `temperature` on every model, takes `output_config.effort`, and answers
  `content` blocks, of which only `type: text` is the answer (a response may start with `thinking` blocks), with
  `stop_reason` `refusal` when its safeguards decline. OpenAI's GPT-5 models refuse `max_tokens` and `temperature`
  and take `max_completion_tokens`.

### User Story 7 - Paid services answer when the free ones cannot (Priority: P2)

The owner adds an OpenAI or Anthropic key as a last resort for a busy day. A paid service answers only once it is
keyed and switched on, and only up to a daily number of paid answers.

**Independent Test**: with only a paid key saved and its switch off, ask a question: the chat is busy. Switch it on
and ask again: it answers. Set the paid cap to 1 and ask twice: the second is busy.

**Acceptance Scenarios**:

1. **Given** a key for OpenAI or Anthropic and its switch untouched, **Then** that service is not asked: paid
   services start off, so a paid key alone never spends money.
2. **Given** a paid service on and keyed, **When** every free service before it rests or fails, **Then** it answers
   the question.
3. **Given** today's paid answers at the paid cap (50 unless set), **Then** every paid service is skipped until the
   next UTC day; a free service still answers.
4. **Given** Claude, **Then** it is asked through Anthropic's own Messages API, with the instructions as `system`,
   no `temperature`, and only its text blocks read; a `refusal` is a refused message (no service rests).
5. **Given** OpenAI, **Then** it is asked with `max_completion_tokens` and without `temperature`.

### User Story 8 - The owner orders the services and switches each (Priority: P2)

**Acceptance Scenarios**:

1. **Given** Settings → Assistant → Services, **Then** the six services are listed in the order the chat asks them,
   each with Move up and Move down, and saving keeps the new order.
2. **Given** a service's switch turned off, **Then** the chat does not ask it, whatever its key.
3. **Given** a fresh site, **Then** the order is Gemini, Groq, OpenRouter, Mistral, OpenAI, Claude, the free ones on
   and the paid ones off.
4. **Given** `ASSISTANT_ORDER` or `ASSISTANT_<ID>_ON` in the site's `.env`, **Then** that value is used and its field
   is locked, as keys are.
5. **Given** the status table, **Then** each service shows Free or Paid, On or Off, Ready, Resting until a time or No
   key, and how it last failed in the past week.

### User Story 9 - A visitor asks for a call at a time that suits both sides (Priority: P2)

**Acceptance Scenarios**:

1. **Given** booking hours on (off by default) with a business time zone, working days, a start, an end, a slot
   length, a minimum notice and a number of days shown, **When** the hand-off form opens, **Then** it offers the
   times inside those hours, from now plus the notice to the last day shown, in the visitor's own time zone,
   grouped by day and then by morning, afternoon and evening, with "Any time" chosen until a time is.
2. **Given** an end at or before the start, **Then** the hours run past midnight into the next day.
3. **Given** a time chosen, **When** the request is sent, **Then** the inbox and the team's email read it in both
   zones, for example "Thu 12 Nov, 7:00 pm Asia/Riyadh = Thu 12 Nov, 6:00 pm Africa/Cairo", with the visitor's
   time zone beside it. It is a requested time the team confirms: nothing is reserved and nothing checks that the
   time was asked for already.
4. **Given** booking hours off, or the times not loaded, **Then** the form keeps its "best time" box.
5. **Given** the times, **Then** they are read when the form opens, not printed in the page, so a cached page never
   offers a time that has passed.

### User Story 10 - The hand-off asks for what the chosen way of reaching the visitor needs (Priority: P1)

**Acceptance Scenarios**:

1. **Given** the hand-off form, **Then** "Reach me by" comes before the number and the email.
2. **Given** Call or WhatsApp, **Then** the number is required and the email optional; given Email, the email is
   required and the number optional; the field that is optional says so.
3. **Given** a request written by hand without the needed field, **Then** the forms engine refuses it too.
4. **Given** a number typed without a dial code being chosen, **Then** the dial code is sent with it; a request with
   no number sends no dial code.

### User Story 11 - The owner sets the limits and the chat's words on the screen (Priority: P2)

**Acceptance Scenarios**:

1. **Given** Settings → Assistant → Limits, **Then** the questions per visitor an hour (20), per visitor a day (60),
   site-wide a day (600) and paid answers a day (50) are whole numbers the owner sets; anything else is refused with
   a sentence saying so.
2. **Given** Settings → Assistant, **Then** for each of the site's languages the owner can write the assistant's name,
   its welcome and three suggested questions; a blank one keeps what the site's code or CoreX says.
3. **Given** "Check services", **Then** each keyed service, on or off, is asked a visitor's question with the real
   instructions and the site's knowledge, inside one 26-second budget, and answers with how long it took, or why it
   failed; a failure is recorded as the chat would record it.

### Edge Cases (slice 2)

- A paid answer is counted after it is given, so two questions in flight at the cap may both be answered.
- A visitor whose browser does not name its time zone sees the times in the browser's own clock and the team reads
  the business's zone twice.
- The business time zone is blank: the site's time zone (Settings → General) is used, an offset such as `+02:00`
  included.
- Daylight saving: the hours are the business's wall clock on each day, so a slot near a clock change is still
  inside the hours that day.
- A site with one language shows one set of branding fields; a language pack installed but not used shows a set
  nobody will see (the `corex_assistant_languages` filter narrows it).

### Functional Requirements (slice 2)

- **FR-016**: The catalogue MUST add OpenAI (`gpt-5-mini`, chat completions, `max_completion_tokens`, no
  `temperature`, `reasoning_effort: low`) and Anthropic Claude (`claude-haiku-5-5`, Messages API, header
  `anthropic-version: 2026-10-21`, `x-api-key`, no `temperature`, `output_config.effort: low`), both marked paid.
  A Messages answer MUST be the text blocks joined; `stop_reason: refusal`, and `max_tokens` with no text, are
  refused messages.
- **FR-017**: A paid service MUST be skipped while today's paid answers are at `assistant.paid_cap` (default 50);
  each paid answer MUST be counted.
- **FR-018**: The chain MUST follow `assistant.order` (a Settings field of a new type, `order`: a list the owner
  rearranges with buttons, saved as the ids joined by commas, unknown ids dropped and missing ones appended) and
  skip a service whose `assistant.<id>.on` is off. Free services default on, paid ones off, drawn as such before the
  first save.
- **FR-019**: Cooldowns MUST keep, per service, until when it rests and its last failure (kind, status, detail,
  when), for seven days; the panel MUST show it.
- **FR-020**: Booking hours MUST be Settings fields: `assistant.booking.enabled` (off), `.timezone` (blank for the
  site's), `.days` (every day, Monday to Friday, Monday to Saturday, Sunday to Thursday, Saturday to Thursday),
  `.start` and `.end` (half hours), `.slot` (15 to 120 minutes), `.notice` (none to two days), `.horizon` (1 to 30
  days). At most 400 slots are offered.
- **FR-021**: `GET corex/v1/assistant/slots` MUST answer `{slots: [UTC seconds]}`, empty while booking is off or the
  assistant is not live, with `Cache-Control: no-store`; it changes nothing and needs no token.
- **FR-022**: The forms engine MUST give a code-defined form a `prepare(array $values): array` step, run after
  validation and before the listeners; the callback form MUST use it to write a chosen slot in both zones.
- **FR-023**: The forms engine MUST have a built-in rule `required_if:<field>,<value>,...`, asked about an absent
  value too; the callback form MUST require the phone for `call` and `whatsapp` and the email for `email`.
- **FR-024**: `assistant.limit.hour`, `assistant.limit.day`, `assistant.daily_cap` and `assistant.paid_cap` MUST be
  Settings fields of a new type, `number` (a whole number of 0 or more, else refused), drawn with their defaults.
- **FR-025**: For each language of the site (its own language and every installed language pack, narrowed or widened
  by the `corex_assistant_languages` filter), `assistant.brand.<language>.name`, `.welcome`, `.suggestion1` to
  `.suggestion3` MUST be Settings fields. What the owner writes there MUST win over the strings filter, which wins
  over CoreX's defaults; `assistant.name` stays the name for every language without its own.
- **FR-026**: "Check services" MUST ask every keyed service, on or off, the site's first suggested question with the
  real system message, each call given an equal share of 26 seconds (at least 4), and MUST record a failure as the
  chat does and clear the rest of a service that answers.

### Success Criteria (slice 2)

- **SC-005**: A site with the behaviour of Perego's revision 2 can drop its own copy: the same services, order,
  switches, paid cap, booking hours, channel-led fields, limits and branding, set on Settings → Assistant.
- **SC-006**: The Pest, Jest and Playwright suites pass, and the four guards run clean on the diff.

## Out of scope

- Storing conversations, analytics on questions, a knowledge editor, voice, file uploads.
- Reserving a slot: calendar sync, double-booking checks, reminders, payment for a call (slice 2 offers a
  requested time only).
- Arabic (or any) translation catalogue: CoreX ships none; a site supplies its strings by the filter or a
  `corex-<locale>.po`.
- Streaming replies.
