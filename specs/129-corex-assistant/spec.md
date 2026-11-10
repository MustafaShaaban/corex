# Feature Specification: CoreX Assistant, a site chat that answers from the site and hands off to the team

**Feature Branch**: `spec/129-corex-assistant`

**Created**: 2026-11-09

**Status**: Specified, planned and built in one pass on the owner's decision relayed from the Perego session
(2026-11-09): "the assistant should be a reusable CoreX feature, not Perego-only". The behaviour below was
designed and approved with the owner for Perego (Perego spec 058, released to peregoads.com on 2026-11-09,
switched off until keys are added). This spec makes it generic. DECISIONS #600 to #607.

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

The owner opens CoreX → Assistant, turns it on, pastes a free key for at least one service and saves. "Check
services" shows which services answer, and how fast.

**Acceptance Scenarios**:

1. **Given** a fresh site, **Then** no button is shown anywhere: the assistant is off by default, and stays hidden
   while on with no key.
2. **Given** a saved key, **Then** it is never drawn back; the field says a key is saved and can be removed.
3. **Given** `ASSISTANT_GROQ_KEY` in the site's `.env`, **Then** that key is used and its field is locked.
4. **Given** keys for several services, **When** "Check services" runs, **Then** each answers "answered" or why
   it did not, and a service that answered is no longer resting.
5. **Given** the page, **Then** each service shows Ready, Resting until a time, Off or No key, and why it last
   failed, and the limits card shows today's questions and those sent to paid services.
6. **Given** the services card, **When** the owner moves a service up or down or switches it off and saves, **Then**
   the chain asks them in that order and skips the ones off; a paid service is off until the owner turns it on.
7. **Given** a key saved on the screen, **Then** the chain uses it, and the services, limits, words and hours
   options are not autoloaded. (Slice 1 also kept the keys unloaded; the Config engine reads only options WordPress
   loads, so a saved key was never used. The keys are stored as every CoreX setting is.)

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
2. **Given** the whole site, **Then** it answers at most the daily cap a day (600 by default, set on the Limits
   card).
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
   language; the name, welcome and suggestions written on the screen for that language are used where the filter
   does not replace them.
4. **Given** a button image chosen on the screen, **Then** it is the button's and the chat's image; else a built-in
   icon.
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
- **FR-004**: The model chain MUST default to Gemini, Groq, OpenRouter, Mistral, over the OpenAI chat completions
  format, then the paid services of FR-016, in the order the owner sets (FR-019); a service with no key or switched
  off is skipped; each has a default model and an optional model setting.
- **FR-005**: Cooldowns per service as User Story 4; kept in one option, not autoloaded.
- **FR-006**: Keys and models MUST be Config dot-keys (`assistant.<service>.key`, `assistant.<service>.model`)
  saved by the CoreX → Assistant screen and readable from `.env` through the Config engine. (Slice 1: Settings
  fields saved with autoload off; superseded, see User Story 3 scenario 7.)
- **FR-007**: The endpoint MUST be `POST corex/v1/assistant`, taking `{locale, messages}` and answering
  `{reply, team, links, busy}`; it MUST run the `nonce` middleware and the assistant's limits middleware, and carry
  `PublicSubmission::MARKER`.
- **FR-008**: Limits as User Story 5, kept in the `corex_assistant_limits` option: 20 an hour, 60 a day per visitor,
  600 a day site-wide and 50 a day sent to paid services, unless the owner sets others.
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
  channel, time, slot, zone, transcript, page), with the forms engine's default listeners, which a site may replace with the
  `corex_assistant_callback_listeners` filter. It MUST be registered only when corex-forms is active.
- **FR-012**: Knowledge MUST come from a `KnowledgeProvider` (`for(string $locale): Knowledge`); the default reads
  the site; the `corex_assistant_knowledge_provider` filter replaces it. Default knowledge MUST be cached per
  language for six hours and dropped when a page is saved.
- **FR-013**: Every visitor-facing string MUST be translatable (`corex` domain) and replaceable per language by the
  `corex_assistant_strings` filter; no string shown to a visitor may name a model or its maker.
- **FR-014** (slice 1, superseded by FR-019): The Settings page "Assistant" MUST show the switch, the assistant's name and mark, the four services'
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
- **SC-004**: Perego can drop its own copy (spec 058 with revision 2) and keep its behaviour by entering its
  settings on CoreX → Assistant and plugging in its knowledge, strings, voice, languages, preview zones, country
  order and callback email (docs: `docs-app/src/content/docs/guides/assistant.md`, "Switching from a site's own copy").

## Slice 2: Perego's revision 2 and the owner's additions (2026-11-09)

Perego's assistant gained a revision 2 on 2026-11-09 while slice 1 was built (`36950af8`, `0bbc9a28`, `50775d97`
on `feature/058-perego-assistant`), and the owner asked, relayed by the Perego session, for the same in CoreX:

- **FR-016** Paid providers as the last resort, off unless the owner turns them on: OpenAI (a mini model;
  GPT-5 models take `max_completion_tokens` and refuse `temperature`) and Anthropic Claude through its own
  Messages API (`POST https://api.anthropic.com/v1/messages`, `x-api-key`, `anthropic-version: 2026-10-21`, no
  `temperature`, `top_p` or `top_k`, `claude-haiku-5-5`, `output_config: {effort: "low"}`, the text of
  `content[]` blocks of type `text`; `stop_reason: "refusal"` is a refused request, 429 a limit, 529 a server
  error). Not an OpenAI-compatible shim. The free tiers stay first. A daily cap on requests sent to paid services (50), each counted whether it answers or not.
- **FR-017** A "pick a time" step in the hand-off: the owner sets working days and hours in the site's own time
  zone (Perego: every day 9:00 to 23:00, Cairo, 30-minute slots); the visitor sees the next days' slots in their
  own time zone, picks one, and the request stores and emails both times ("Thu 7:00 pm Riyadh = 6:00 pm Cairo").
  A requested time the team confirms, not a booking. Themed with the site's tokens.
- **FR-018** A call or WhatsApp needs a number and an email an address; the other is optional, in the browser and
  on the server (`required_for`; the engine's `required_if` from slice 3, FR-023).
- **FR-019** The settings are a CoreX screen of their own (`corex-assistant` in the CoreX rail, drawn with
  `AdminPage` and `.corex-surface` cards): Assistant (switch, image, per-language name, welcome and suggestions),
  Services (owner's order, a switch each, key, model, status and last failure), Booking (time zone, opening and
  closing, slot length, notice, days shown, working days, a preview in other zones), Limits (per visitor an hour
  and a day, site-wide and sent to paid services a day), and "Check services" with a real question, timed. This replaces
  slice 1's page under Settings (FR-014).
- **FR-020** Every option the screen saves is stored with autoload off but the switch, and every cleaning step
  reads its own output back unchanged (Perego met WordPress running a setting's sanitize twice on first save).

CoreX Bookings was weighed as the home of the slot logic and not taken: add-ons may not depend on one another, and
Bookings' leader-based call requests are a different model (DECISIONS #612).

## Slice 3: a time asked for once, a line the server writes, `required_if` (2026-11-10)

DECISIONS #616 left three pieces for this slice. Perego's live assistant has the first (a `perego_slot_free`
rule over one option holding a list of times, and a fresh list in the chat when it refuses); the owner wants it
in the add-on. Perego's list is read, changed and written back, so two visitors sending at once can both be
given the same time, and a request the team binned keeps its time hidden until it passes. This slice does
neither.

### User Story 7 - A time one visitor asked for is not offered to the next (Priority: P1)

A visitor picks Thursday 7:00 pm and sends. The next visitor who opens the hand-off does not see Thursday
7:00 pm. Two visitors who send the same time at once: one request is taken, the other visitor is told the time
was just taken and shown the times still open.

**Independent Test**: send a request for a time through the form route, then ask the slots route; send the same
time again and read the refusal; trash the first request in the Submissions inbox and ask the slots route again.

**Acceptance Scenarios**:

1. **Given** a request stored for a time, **When** anyone asks `GET corex/v1/assistant/slots`, **Then** that
   time is not in the list.
2. **Given** a time already asked for, or one no longer offered (past the notice, outside the hours, booking
   off, not a slot at all), **When** a request names it, **Then** the route answers 422 with
   `errors.slot = "slot_taken"`, nothing is stored and no email is sent.
3. **Given** that answer, **Then** the chat says the time was just taken, fetches the times again and shows them
   with no time chosen; with none left it shows the typed "best time" field.
4. **Given** two requests for one time arriving together, **Then** exactly one is stored; the other gets the
   answer of scenario 2.
5. **Given** a stored request, **When** it is moved to the trash or deleted for good (by hand, in bulk or by
   retention), **Then** its time is offered again. **When** it is restored and its time is still free, **Then**
   the time is taken again; if another visitor has asked for it since, the restore still succeeds and both
   requests name the time.
6. **Given** a request whose storage failed, or a site whose `corex_assistant_callback_listeners` filter stores
   nothing, **Then** no time is hidden by it.
7. **Given** "any time" or a typed time, **Then** nothing is claimed and nothing is refused.

### User Story 8 - The team reads a time the server wrote (Priority: P1)

The inbox and the team's email show "Thu 12 Nov, 7:00 pm Riyadh = Thu 12 Nov, 6:00 pm Cairo", written by the
server from the moment and the visitor's zone, in the site's language. A request written by hand cannot put a
made-up line there.

**Acceptance Scenarios**:

1. **Given** a request with a `slot` and a known `zone`, **Then** the stored `time` is the server's line, the
   visitor's zone first, then the booking hours' zone, each with its city; with the same zone, or a zone the
   server does not know, only the booking hours' time.
2. **Given** a request with a `time` the browser wrote and a `slot`, **Then** the browser's `time` is replaced.
3. **Given** a request with no `slot`, **Then** `time` is what the visitor typed, kept as typed.
4. **Given** a slot, **Then** the stored `slot` is the moment in UTC, written `2026-11-12T16:00:00Z`; `zone` is
   kept only when the server knows it.
5. **Given** a site in another language (`de_DE`), **Then** the weekday and month are that language's.

### User Story 9 - A form says which answer makes a field required (Priority: P2)

A form developer writes `'rules' => ['required_if:channel,call,whatsapp']`. The browser and the server ask for
the field only while `channel` is one of those answers.

**Acceptance Scenarios**:

1. **Given** `required_if:channel,call,whatsapp` on `phone`, **When** `channel` is `call` or `whatsapp` and
   `phone` is empty or left out of the request, **Then** the server answers `phone: "required"`; with `email`, no
   error.
2. **Given** the same rule in a CoreX form block, **Then** the browser gives the same answer before sending.
3. **Given** no field to follow or no answers listed, **Then** the rule asks for nothing.
4. **Given** the assistant's callback form, **Then** it uses `required_if`; the add-on's own `required_for` rule
   is gone.

### Functional Requirements (slice 3)

- **FR-021** Every time requested through `corex-assistant-callback` MUST be claimed once, in the site's
  database, by a write only one request can win (a row with a unique name, `INSERT IGNORE`), during the forms
  engine's prepare step (FR-022), after every other answer has passed. A claim is pending until the submission
  is stored, then names the submission; a request nothing stored releases its claim. Claims of past times are
  removed when a new claim is made. `GET corex/v1/assistant/slots` and the `assistant_slot` rule MUST leave out
  claimed times. Moving a submission to the trash or deleting it MUST release its claim; restoring it MUST claim
  its time again when the time is still free and in the future.
- **FR-022** The forms engine MUST give a code-defined form `Form::prepare(array $values): array`, run once per
  valid submission, after validation and after uploads are stored, before any listener; what it returns is what
  is stored and sent, less any field the form does not declare. A form MAY refuse there by throwing
  `Corex\Forms\Validation\AnswersRefused` with field errors; the engine then answers 422 in the shape of a
  validation failure and forgets any upload it stored for that request. The assistant's form writes `time`,
  `slot` and `zone` there (User Story 8) with `wp_date()` in the site's language.
- **FR-023** `required_if:<field>,<value>,...` MUST be a built-in rule of the forms engine, a
  `RuleForAbsentValue`, answering the `required` key; the runtime script MUST apply it the same way, to a field
  the form left out too. The add-on's `required_for` is removed.
- **FR-024** The booking hours MUST accept the site's time zone written as an offset (`+02:00`), which is what
  WordPress gives a site set to "UTC+2".

## Out of scope

- Storing conversations, analytics on questions, a knowledge editor, voice, file uploads.
- Calendar sync, reminders, payments for a picked time. A requested time is still one the team confirms: slice 3
  stops two visitors asking for the same time, it does not book it.
- Clearing a page cache when the assistant is switched on or off (Perego purges LiteSpeed; CoreX knows no page
  cache by name).
- Arabic (or any) translation catalogue: CoreX ships none; a site supplies its strings by the filter or a
  `corex-<locale>.po`.
- Streaming replies.
