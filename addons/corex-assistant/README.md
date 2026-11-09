# Corex Assistant

A chat on every public page that answers visitors from the site's own content and hands them to the team when
it cannot help (spec 129). Optional add-on; builds on corex-core, and on corex-forms for the callback form.

The full guide, for site owners and developers, is `docs-app/src/content/docs/guides/assistant.md`
(`/guides/assistant/` on the docs site).

## In short

- **Off by default.** Activate it, turn on **CoreX → Assistant → Show the assistant**, and save a free key for
  at least one of Google Gemini, Groq, OpenRouter or Mistral. Keys can be set in `.env`
  (`ASSISTANT_GROQ_KEY=...`), which locks the field. OpenAI and Claude can follow as paid last resorts, off
  until the owner turns them on, capped at 50 requests a day by default.
- **The chain.** Services are asked in that order; one that is out of its free limit rests until it may be asked
  again, and the next answers. When none can, the chat opens its callback form.
- **The route.** `POST /wp-json/corex/v1/assistant`, `{locale, messages}` in, `{reply, team, links, busy}` out,
  behind the page's REST token and the assistant's limits. `POST /wp-json/corex/v1/assistant/check` is "Check
  services", administrators only.
- **The callback.** The CoreX form `corex-assistant-callback`, stored in Submissions and notified to the team.
  With booking on, the visitor picks a time from `GET /wp-json/corex/v1/assistant/slots`, shown in their zone.

## Extending it

| Filter | What it takes |
|---|---|
| `corex_assistant_languages` | the languages the owner writes the assistant's words in on the screen |
| `corex_assistant_preview_zones` | the zones the screen previews the booking hours in |
| `corex_assistant_knowledge_provider` | a `Corex\Assistant\Knowledge\KnowledgeProvider`: the site's own words and pages per language |
| `corex_assistant_strings` | `(array $strings, string $locale)`: any visitor string, per language |
| `corex_assistant_voice` | `(array $rules, string $language)`: short rules for how replies read (a dialect, words to avoid) |
| `corex_assistant_phone_countries` | `['default' => 'AE', 'first' => ['AE', 'SA']]`: the callback form's countries |
| `corex_assistant_callback_listeners` | the callback form's listeners (container ids): a site's own email in place of CoreX's |

Colours: set `--corex-assistant-*` on `.corex-assistant` (listed at the top of `blocks/assistant/style.scss`).

## Layout

| Path | What it holds |
|---|---|
| `src/Chain/` | the services, their failures and rests, one request to one service, the chain |
| `src/Knowledge/` | `Knowledge`, the `KnowledgeProvider` contract, CoreX's default `SiteKnowledge` |
| `src/Reply/` | the standing instructions, the reply shaper, the conversation a visitor may send |
| `src/Limits/`, `src/Http/` | the visitor key, counts and lock, their middleware, the chat route |
| `src/Front/`, `src/Forms/` | the button and its strings, the callback form |
| `src/Admin/` | CoreX → Assistant (screen, view, save) and "Check services" |
| `src/Booking/` | the booking hours and the slots route |
| `blocks/assistant/` | `view.js` (the button) and the lazily loaded `chat.js`, `dom.js`, `handoff.js`, `dial-codes.js` |

Build with `npm run build -w addons/corex-assistant`.
