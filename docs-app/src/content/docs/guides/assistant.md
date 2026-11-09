---
title: Site assistant
description: A chat on every public page that answers from the site's own content and hands visitors to the team.
---

The optional **Corex Assistant** add-on (spec 129) puts a round chat button on every public page. A visitor
asks a question and gets a short answer drawn from your site's own content, with links to your pages. When it
cannot help (a price, a timeline, a person), it opens a callback form inside the chat, and the request arrives
in **Submissions** like any CoreX form.

It runs on the free tiers of four AI services, asked in turn: when one's free limit is spent, the next answers.
It says on screen that it is an AI that answers from the site only and can be wrong, and no visitor ever sees
which service answered.

## Switch it on

1. **Add-ons**: activate **Corex Assistant**.
2. **Settings → Assistant**: turn on **Show the assistant**.
3. Paste a free API key for at least one service and save. Each field links to the page where the service gives
   one: Google Gemini (Google AI Studio), Groq, OpenRouter, Mistral. One key is enough; more keys mean more free
   questions a day before the chat falls back to its callback form.
4. Press **Check services**. Each keyed service answers "answered" or says why it did not.

Free tiers of some of these services may use what is sent to them to improve their models. The chat sends only
the conversation and your public pages, and its note asks visitors not to share sensitive information.

Nothing shows on the site until it is switched on **and** at least one service has a key. A saved key is never
shown again; **Remove** takes it away.

Keys can also live in the site's `.env`, which then locks the field on the screen:

```dotenv
ASSISTANT_GEMINI_KEY=...
ASSISTANT_GROQ_KEY=...
ASSISTANT_OPENROUTER_KEY=...
ASSISTANT_MISTRAL_KEY=...
```

Any Settings value works the same way (`assistant.daily_cap` is `ASSISTANT_DAILY_CAP`). Keys saved on the
screen are stored with autoload off, so they are not loaded with every request; while the assistant is on, a public
page reads them only to decide whether to show the button.

## What it answers, and how it stays honest

Every question is sent with standing instructions that cannot be changed from the chat. The assistant:

- answers only from the knowledge it is given (below), and says when it does not know;
- never invents prices, durations, discounts, clients, results or guarantees;
- reads misspelled, mixed-language or half-written questions generously, and recommends a service when the
  visitor describes a goal instead of naming one;
- replies in the language of the visitor's last message (or the language they asked for: "reply in English",
  "كلمني بالعربي"), whatever the page's language, briefly, in plain paragraphs;
- links only to pages the knowledge names: any other web address in a reply is removed, and so are email
  addresses and international phone numbers;
- declines unrelated requests and attempts to change its role;
- offers the team when a visitor asks for a person, a price or a call.

The conversation stays in the visitor's browser tab (session storage) and is never stored on the server. When a
visitor sends the callback form, the chat goes with it if they leave "Attach a summary of this chat" ticked.

## Limits

Each answer costs a model call, so the chat is limited on the server:

| Limit | Value |
|---|---|
| A visitor (a salted hash of their address; an IPv6 /64 network counts as one) | one question at a time, 20 in a clock hour, 60 in a UTC day |
| The whole site | 600 in a UTC day, or **Questions a day, whole site** in Settings |
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
| a server error, a timeout, no answer at all | one minute |
| a refusal of this message (its safety filter, a message it cannot take) | not at all |

## Give it better knowledge

Out of the box the assistant knows your site's name, tagline and up to 40 published pages: each page's title,
address and the start of its text. That is a fair start for a small site. A site that has a written profile of
itself (an `llms.txt`, an "about us" text, its services in its own words) gives the assistant that instead,
through a **knowledge provider**:

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

- `$locale` is the page's language (`en`, `ar`, ...), so a bilingual site returns each language's own copy and
  page addresses.
- The lines are free text; Markdown links inside them to this site's pages count as pages the assistant may link
  to.
- The pages are what a reply's link chips may point to. Nothing else can be linked.
- The whole knowledge goes with every question, so keep it to the facts a visitor asks about (tens of
  kilobytes at most). A provider that builds it from the database should keep the result, as the default keeps
  its own for six hours.

Add the filter on `init` or earlier.

## Its name, words and image

**Settings → Assistant** sets the **Assistant name** (blank: your site's name followed by "Assistant") and the
**Assistant image** on the button. Every word a visitor reads can be replaced per language:

```php
add_filter('corex_assistant_strings', static function (array $strings, string $locale): array {
    if ($locale !== 'ar') {
        return $strings;
    }

    return [
        'name' => 'مساعد الاستوديو',
        'welcome' => 'أهلًا بك! كيف أخدمك؟',
        'suggestions' => ['ما الخدمات التي تقدّمونها؟', 'كيف أبدأ مشروعًا معكم؟'],
        'note' => 'مساعد ذكي يجيب من محتوى موقعنا فقط، وقد يخطئ. لا تشارك بيانات حساسة هنا.',
    ] + $strings;
}, 10, 2);
```

The keys are those of `Corex\Assistant\Front\AssistantStrings`: `name`, `status`, `open`, `teaserTitle`,
`teaserText`, `dismiss`, `welcome`, `suggestions` (a list), `placeholder`, `send`, `note`, `human`, `newChat`,
`close`, `typing`, `busy`, `error`, `expired`, `handoffTitle`, `handoffText`, the field labels (`fieldName`,
`fieldPhone`, `fieldCountry`, `fieldEmail`, `fieldChannel`, `fieldTime`), `channelCall`, `channelWhatsapp`,
`channelEmail`, `attach`, `submit`, `sending`, `formError`, `nameNeeded`, `phoneNeeded`, `emailNeeded` and `done`
(keep its `%s`, the visitor's name). A key you leave out keeps CoreX's English, or its translation in your
`corex-<locale>.po`. Keep the honesty `note`, and name no AI company in any string.

How replies read (a dialect to write in, words to avoid) is a list of short rules per language:

```php
add_filter('corex_assistant_voice', static function (array $rules, string $language): array {
    return $language === 'ar'
        ? [...$rules, 'Write as a Saudi or Gulf company writes to its clients, never in Egyptian dialect.']
        : $rules;
}, 10, 2);
```

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

## The callback form

The hand-off is the CoreX form `corex-assistant-callback`: name, phone with its country code, email (optional),
call / WhatsApp / email, the best time, the chat, and the page it was sent from. It has the forms engine's
honeypot, checks and storage, and the team is notified at **Settings → Email → Form notification recipient**
(else the site administrator), with Reply-To set only when the visitor gave an email. The visitor is answered in
the chat, not by email. Without the Corex Forms plugin the chat works and offers no form.

A site that sends its own designed emails puts its listener in place of CoreX's notification, and keeps
storage:

```php
use Corex\Forms\Listeners\StoreSubmissionListener;

add_filter('corex_assistant_callback_listeners', static fn (): array => [
    StoreSubmissionListener::class,
    \Acme\Email\CallbackMail::class, // invoked with the Corex\Forms\Submission\FormSubmittedEvent
]);
```

Each entry is a class the CoreX container can build, as for any CoreX form's `listeners()`.

The country code is preset from the visitor's time zone, then their browser's language, then the site's default
country. A site chooses its default country and the countries listed
first:

```php
add_filter('corex_assistant_phone_countries', static fn (): array => [
    'default' => 'AE',
    'first' => ['AE', 'SA', 'KW', 'QA', 'BH', 'OM', 'EG'],
]);
```

## Caching and the page token

The button carries the page's REST token, as CoreX forms do; WordPress keeps one valid for 12 to 24 hours. A page
cache that keeps pages longer than 12 hours will serve some visitors an expired token; the chat then asks them to
refresh. Keep such pages' cache under 12 hours.

## Switching from a site's own copy

This add-on is Perego's spec 058 assistant, generalised. A site that built its own copy switches in one change, so
two buttons never show:

1. Activate the add-on and paste the same keys on **Settings → Assistant**, or as `ASSISTANT_<SERVICE>_KEY` in
   `.env` (and delete any old `wp-config.php` constants).
2. Return its knowledge from a `KnowledgeProvider` on `corex_assistant_knowledge_provider`.
3. Move its strings into `corex_assistant_strings`, its voice rules into `corex_assistant_voice`, its image into
   **Assistant image**, its country order into `corex_assistant_phone_countries`, its colours into the
   `--corex-assistant-*` properties, and its own callback email (if it has one) into
   `corex_assistant_callback_listeners`.
4. Remove its own chat route, block, settings screen, callback form and the callback branch of its mail listener.

Its old callback submissions stay in the inbox under their old form name.

**Not in the add-on yet** (Perego's revision 2 of 2026-11-09, the next slice of spec 129): paid fallbacks (OpenAI,
Claude) with a daily cap for paid answers; the owner choosing the services' order and switching each on or off;
booking hours with time slots shown in the visitor's time zone; required fields that follow the chosen channel;
limits and branding edited on the screen. A site that uses any of these keeps its own copy until that slice lands.
