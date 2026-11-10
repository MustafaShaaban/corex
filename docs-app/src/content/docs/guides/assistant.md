---
title: Site assistant
description: A chat on every public page that answers from the site's own content and hands visitors to the team.
---

The optional **Corex Assistant** add-on (spec 129) puts a round chat button on every public page. A visitor
asks a question and gets a short answer drawn from your site's own content, with links to your pages. When it
cannot help (a price, a timeline, a person), it opens a callback form inside the chat, where the visitor can also
pick a time; the request arrives in **Submissions** like any CoreX form.

It runs on the free tiers of four AI services asked in turn: when one's free limit is spent, the next answers.
Two paid services (OpenAI and Anthropic's Claude) can be added as a last resort, with a daily cap. The chat says
on screen that it is an AI that answers from the site only and can be wrong, and no visitor ever sees which
service answered.

## Switch it on

1. **Add-ons**: activate **Corex Assistant**. It adds **Assistant** to the CoreX menu.
2. **CoreX → Assistant**: turn on **Show the assistant**.
3. In **Services**, paste a free API key for at least one service and save. Google Gemini (Google AI Studio),
   Groq, OpenRouter and Mistral give one free; one key is enough, and more keys mean more free questions a day.
4. Press **Check services**. Each service with a key, on or off, is asked a visitor's question with the full
   instructions, and the screen says which answered and how fast. A paid service is charged for that question,
   and it counts against today's paid limit.

Nothing shows on the site until it is switched on **and** at least one service is on with a key. A saved key is
never shown again; tick **Remove** and save to take it away.

Free tiers of some of these services may use what is sent to them to improve their models. The chat sends only
the conversation and your public pages, and its note asks visitors not to share sensitive information.

Keys and models can also live in the site's `.env`, which then locks them on the screen:

```dotenv
ASSISTANT_GEMINI_KEY=...
ASSISTANT_GROQ_KEY=...
ASSISTANT_OPENROUTER_KEY=...
ASSISTANT_MISTRAL_KEY=...
ASSISTANT_OPENAI_KEY=...
ASSISTANT_ANTHROPIC_KEY=...
ASSISTANT_GEMINI_MODEL=gemini-flash-lite-latest
```

The switch, the keys and the models are stored as every CoreX setting is, so the site's `.env` can lock them. The
services, limits, words and booking hours are stored with autoload off: while the assistant is on, a public page
reads them, with the keys, to decide whether to show the button and how it looks.

## The screen

**Assistant.** The switch, the image on the button (from the media library; blank is a speech-bubble icon), and
for each language its name, its welcome line and up to three suggested questions. A field left blank keeps the
built-in words. The languages are the site's and English; a site in another language as well adds it:

```php
add_filter('corex_assistant_languages', static fn (array $languages): array => [...$languages, 'ar']);
```

**Services.** One row per service, asked from the top. Move a row up or down, switch a service on or off, paste
its key, and change its model only when the service retires the one CoreX names. Each row says whether the
service is ready, resting until a time, off, or has no key, and why it last failed. Free services start on and
paid ones off: a paid key alone never spends money. Keep paid services last.

**Booking.** When the team takes calls: the time zone, the working days, opening and closing times (a closing
time at or before the opening time closes the next day), the slot length, the notice a visitor must give and how
many days ahead they may pick. The card shows the hours as visitors in other zones see them; name those zones with
`corex_assistant_preview_zones`:

```php
add_filter('corex_assistant_preview_zones', static fn (): array => ['Asia/Riyadh', 'Asia/Dubai', 'Africa/Cairo']);
```

**Limits.** Questions a visitor may ask in a clock hour and in a UTC day, questions the whole site answers in a
day, and questions sent to paid services in a day (each counts, answered or not).

**Check services.** As in step 4 above.

## What it answers, and how it stays honest

Every question is sent with standing instructions that cannot be changed from the chat. The assistant:

- answers only from the knowledge it is given (below), and says when it does not know;
- reads misspelled, mixed-language or half-written questions generously, and recommends a service when the
  visitor describes a goal instead of naming one;
- never invents prices, durations, discounts, clients, results or guarantees;
- replies in the language of the visitor's last message (or the language they asked for: "reply in English",
  "كلمني بالعربي"), whatever the page's language, briefly, in plain paragraphs;
- links only to pages the knowledge names: any other web address in a reply is removed, and so are email
  addresses and international phone numbers;
- declines unrelated requests and attempts to change its role;
- offers the team when a visitor asks for a person, a price or a call.

The conversation stays in the visitor's browser tab (session storage) and is never stored on the server. When a
visitor sends the callback form, the chat goes with it if they leave "Attach a summary of this chat" ticked.

## Limits and rests

| Limit | Default |
|---|---|
| A visitor (a salted hash of their address; an IPv6 /64 network counts as one) | one question at a time, 20 in a clock hour, 60 in a UTC day |
| The whole site | 600 questions in a UTC day |
| Paid services | 50 questions sent in a UTC day, answered or not |
| One question | 600 characters; the last 12 messages are sent |
| One request | 16,000 bytes, refused unread above that |

Past a limit, the chat says it cannot answer now and opens the callback form. Every visitor behind one address (an
office, a mobile carrier's shared address) shares that address's limits.

Each call to a service may take 10 seconds, and the whole search 22. A service that fails rests:

| The service answered | It rests |
|---|---|
| "limit reached" with a time | until that time |
| "limit reached" with none | one minute |
| its daily limit | until the next UTC midnight |
| a key, credit or model error | one hour |
| a server error, an overload, a timeout, no answer at all | one minute |
| a refusal of this message (its safety filter, a message it cannot take) | not at all |

## The services

| Service | Kind | Default model | Notes |
|---|---|---|---|
| Google Gemini | free tier | `gemini-flash-lite-latest` | OpenAI-compatible endpoint |
| Groq | free tier | `openai/gpt-oss-120b` | |
| OpenRouter | free tier | `openrouter/free` | |
| Mistral | free tier | `mistral-small-latest` | |
| OpenAI | paid | `gpt-5-mini` | sent `max_completion_tokens` and no `temperature`, as GPT-5 models require |
| Anthropic Claude | paid | `claude-haiku-5-5` | asked through Anthropic's own Messages API (`anthropic-version: 2026-10-21`), with low effort and no sampling settings |

## The callback form and picking a time

The hand-off is the CoreX form `corex-assistant-callback`: name, how to reach them (call, WhatsApp or email), phone
with its country code, email, the time, the chat and the page it was sent from. A call or WhatsApp needs a number,
an email needs an address, and the other is optional; the server checks the same with the `required_for` rule. It
has the forms engine's honeypot, checks and storage, and the team is notified at **Settings → Email → Form
notification recipient** (else the site administrator), with Reply-To set only when the visitor gave an email. The
visitor is answered in the chat, not by email. Without the Corex Forms plugin the chat works and offers no form.

With booking on, the form lists the next times in the visitor's own time zone, grouped by day, and "Any time that
suits the team". A picked time is stored three ways: `time`, the line the team reads ("Thu, Nov 12, 7:00 PM Riyadh
= Thu, Nov 12, 6:00 PM Cairo"); `slot`, the moment in UTC; and `zone`, the visitor's time zone. It is a time the
team confirms, not a reservation: nothing checks for a time already asked for. With booking off, the visitor types
a best time instead.

The country code is preset from the visitor's time zone, then their browser's language, then the site's default.
A site chooses its default country and the countries listed first:

```php
add_filter('corex_assistant_phone_countries', static fn (): array => [
    'default' => 'AE',
    'first' => ['AE', 'SA', 'KW', 'QA', 'BH', 'OM', 'EG'],
]);
```

A site that sends its own designed emails puts its listener in place of CoreX's notification, and keeps storage:

```php
use Corex\Forms\Listeners\StoreSubmissionListener;

add_filter('corex_assistant_callback_listeners', static fn (): array => [
    StoreSubmissionListener::class,
    \Acme\Email\CallbackMail::class, // invoked with the Corex\Forms\Submission\FormSubmittedEvent
]);
```

Each entry is a class the CoreX container can build, as for any CoreX form's `listeners()`.

## Give it better knowledge

Out of the box the assistant knows your site's name, tagline and up to 40 published pages: each page's title,
address and the start of its text. A site that has a written profile of itself (an `llms.txt`, an "about us" text,
its services in its own words) gives the assistant that instead, through a **knowledge provider**:

```php
use Corex\Assistant\Knowledge\Knowledge;
use Corex\Assistant\Knowledge\KnowledgeProvider;

final class StudioKnowledge implements KnowledgeProvider
{
    public function for(string $locale): Knowledge
    {
        return new Knowledge(
            $locale,
            'Example Studio',
            [
                'Example Studio designs websites and brands for small businesses.',
                '- [Book a free call](https://example.com/contact/?intent=call)',
            ],
            [
                ['label' => 'Websites', 'url' => 'https://example.com/services/websites/'],
                ['label' => 'Contact', 'url' => 'https://example.com/contact/'],
            ],
        );
    }
}

add_filter('corex_assistant_knowledge_provider', static fn () => new StudioKnowledge());
```

- `$locale` is the language of the visitor's last message (`en`, `ar`, ...), so a bilingual site returns each
  language's own copy and page addresses.
- The lines are free text; Markdown links inside them to this site's pages count as pages the assistant may link
  to.
- The pages are what a reply's link chips may point to. Nothing else can be linked.
- The whole knowledge goes with every question, so keep it to the facts a visitor asks about (tens of
  kilobytes at most). A provider that builds it from the database should keep the result, as the default keeps
  its own for six hours.
- The default is built again at once when a page is saved, published, unpublished, binned or deleted, when the
  site's name or tagline changes, and when an update of WordPress, a plugin or a theme finishes. Each time it
  fires `corex_assistant_knowledge_forgotten`, so your provider can drop its own copy then. When your own
  content changes (a project, a price list), fire `do_action('corex_assistant_forget_knowledge')`.

Add the filter on `init` or earlier.

## Its words and voice in code

The screen covers the name, welcome and suggestions. Every other word a visitor reads can be replaced per
language, and a site can add rules for how replies read:

```php
add_filter('corex_assistant_strings', static function (array $strings, string $locale): array {
    return $locale === 'ar'
        ? ['note' => 'مساعد ذكي يجيب من محتوى موقعنا فقط، وقد يخطئ. لا تشارك بيانات حساسة هنا.'] + $strings
        : $strings;
}, 10, 2);

add_filter('corex_assistant_voice', static function (array $rules, string $language): array {
    return $language === 'ar'
        ? [...$rules, 'Write as a Saudi or Gulf company writes to its clients, never in Egyptian dialect.']
        : $rules;
}, 10, 2);
```

The string keys are those of `Corex\Assistant\Front\AssistantStrings`: `name`, `status`, `open`, `teaserTitle`,
`teaserText`, `dismiss`, `welcome`, `suggestions` (a list), `placeholder`, `send`, `note`, `human`, `newChat`,
`close`, `typing`, `busy`, `error`, `expired`, `handoffTitle`, `handoffText`, the field labels (`fieldName`,
`fieldPhone`, `fieldCountry`, `fieldEmail`, `fieldChannel`, `fieldTime`, `fieldSlot`), `optional`, `slotAny`,
`slotZone` (keep its `%s`, the visitor's zone), `channelCall`, `channelWhatsapp`, `channelEmail`, `attach`,
`submit`, `sending`, `formError`, `nameNeeded`, `phoneNeeded`, `emailNeeded` and `done` (keep its `%s`, the
visitor's name). Order of precedence: CoreX's English (or your `corex-<locale>.po`), then the screen, then the
filter. Keep the honesty `note`, and name no AI company in any string.

## Its colours

The chat reads your theme's tokens (`primary`, `surface`, `ink`, `border` and the radius, spacing and motion
presets). A theme that names its colours differently sets the chat's own properties:

```css
.corex-assistant {
	--corex-assistant-accent: var(--wp--preset--color--brand);
	--corex-assistant-on-accent: var(--wp--preset--color--on-brand);
	--corex-assistant-surface: var(--wp--preset--color--card);
}
```

The full list is at the top of the add-on's `blocks/assistant/style.scss`. It is right to left on a page that is
(`is_rtl()`), and it moves nothing for a visitor who asks for reduced motion.

## Caching and the page token

The button carries the page's REST token, as CoreX forms do; WordPress keeps one valid for 12 to 24 hours. A page
cache that keeps pages longer than 12 hours will serve some visitors an expired token; the chat then asks them to
refresh. Keep such pages' cache under 12 hours. Turning the assistant on or off, or changing its look, reaches
cached pages only when that cache is cleared.

## Switching from a site's own copy

This add-on is Perego's spec 058 assistant, generalised. A site that built its own copy switches in one change, so
two buttons never show:

1. Activate the add-on and enter the same keys, order, switches, limits, booking hours and words on **CoreX →
   Assistant** (or keys as `ASSISTANT_<SERVICE>_KEY` in `.env`, deleting any old `wp-config.php` constants).
2. Return its knowledge from a `KnowledgeProvider` on `corex_assistant_knowledge_provider`.
3. Move its other strings into `corex_assistant_strings`, its voice rules into `corex_assistant_voice`, its second
   language into `corex_assistant_languages`, its preview zones into `corex_assistant_preview_zones`, its country
   order into `corex_assistant_phone_countries`, its colours into the `--corex-assistant-*` properties, and its own
   callback email (if it has one) into `corex_assistant_callback_listeners`.
4. Remove its own chat route, block, settings screen, callback form and rule, and the callback branch of its mail
   listener.

Its old callback submissions stay in the inbox under their old form name.
