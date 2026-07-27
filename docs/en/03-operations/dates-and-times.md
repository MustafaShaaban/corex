# Dates and times in the CoreX admin

Every date shown anywhere in the CoreX administration is produced by one contract, so the same
moment reads the same on every screen, in every language, whether the screen was rendered by the
server or by the browser.

## What a date looks like

In English, a full date and time reads:

```text
1 August 2026 at 10:20 PM
```

Day without a leading zero, the month spelled out, a four-digit year, a connector, a 12-hour clock
with minutes and a meridiem — and **no seconds**. Seconds appear only in technical diagnostics,
where the difference between 10:20:04 and 10:20:57 is the thing you are looking at.

Five presentations exist and no others:

| Presentation | Example | Used for |
|---|---|---|
| Full | `1 August 2026 at 10:20 PM` | the ordinary case |
| Date | `1 August 2026` | where a time would be noise, such as a due date |
| Time | `10:20 PM` | where the date is established by its surroundings |
| Relative | `2 hours ago` | recency, with the exact moment beside it |
| Exact | `1 August 2026 at 10:20:24 PM` | diagnostics only |

## The site timezone is the only source of truth

A date is displayed in the timezone configured at **Settings → General**, and nothing else
influences it. Not the server's timezone, not the reader's browser, not UTC.

This matters more than it sounds. Before this contract, six admin surfaces formatted dates in the
reader's own browser timezone, so two colleagues in different countries read *different times for
the same event* — and neither was told which zone they were looking at. A submission that arrived
at 09:00 site time showed as 09:00 to one of them and 18:00 to the other.

CoreX handles all three ways WordPress can be configured:

- **A named timezone** such as `Africa/Cairo`, including its daylight-saving changes. A record
  stored in August displays at +03:00 and one stored in January at +02:00, without anyone telling
  it which applies.
- **A raw UTC offset** such as `UTC+3`, which WordPress permits. Such a site has no daylight-saving
  rules — that is a property of how it was configured, not a simplification.
- **UTC itself**, where UTC display is correct rather than a fallback.

## Machine values and display values are different things

Nothing about your stored data changes. CoreX writes timestamps in UTC ISO 8601
(`2026-08-01T19:20:00+00:00`) and its REST responses continue to carry exactly that. Human
formatting happens only at the moment of display.

Every visible date is rendered as a semantic `<time>` element:

```html
<time datetime="2026-08-01T19:20:00+00:00">1 August 2026 at 10:20 PM</time>
```

The visible text is for people; the `datetime` attribute is for screen readers, browsers, and
anything that parses the page. When there is no date to show, CoreX renders a plain `<span>`
instead — a `<time>` with an empty or invented `datetime` would tell every parser that a
machine-readable date is present when it is not.

**Sorting always uses the machine value.** A column of dates orders chronologically, never
alphabetically by month name — which would put April before December.

## Missing and unusable dates

A date that is absent says so, in words chosen for what that field means: *Never updated*, *No
expiry*, *Not recorded*, *Never edited*, *Time not recorded*. These are different statements and
CoreX does not flatten them into one.

What you will never see is a date that is not real:

- `Invalid Date` or `NaN`
- `1 January 1970` — the Unix epoch, which is what a null becomes when it is read as a number
- today's date standing in for a value that failed to parse
- a blank cell that collapses the row it is in

CoreX is deliberately strict about what counts as a timestamp. A stored `0`, a bare `2026` (a year,
not a moment), a truncated `2026-08`, and the literal string `now` are all treated as *absent*
rather than converted into a plausible-looking date. A wrong date that looks right is worse than an
honest gap, because nobody checks it.

## Languages

Month names, meridiem markers and the connector all come from the active WordPress locale. In
Arabic the whole string is Arabic — the English word "at" appears nowhere — and it reads correctly
right-to-left.

Translators control the full pattern, not fragments of it. The connector is translated as
`%1$s at %2$s`, so a locale that needs the time first can write `%2$s — %1$s` and both halves of
the product follow. The date and time formats are themselves translatable strings (`j F Y` and
`g:i A`), so a locale that writes dates differently changes them once.

## CoreX admin dates do not follow Settings → General

This is the one thing likely to surprise an existing site owner.

WordPress's **Settings → General** date and time format options govern posts, comments and the rest
of wp-admin. They do **not** govern CoreX admin screens, which always use the format above.

That is deliberate. CoreX renders the same data from two places — PHP for server-rendered screens,
JavaScript for the React workspaces — and they have to agree character for character. A
per-site-configurable format makes that impossible to guarantee, and the previous behaviour proved
it: three server-side surfaces built their format from those options and produced
`July 27, 2026 8:53 am`, which no browser-side renderer was ever going to match.

Your front-end site and the rest of wp-admin are unaffected.

## For developers

Server side, inject the contract or use the facade in view code:

```php
use Corex\Support\DateTime\AdminDateTime;
use Corex\Support\Facades\AdminDate;

// Injected, wherever there is a constructor:
$when = $this->dateTime->format($record->createdAt, AdminDateTime::FULL, __('Not recorded', 'corex'));
echo $when->toHtml('my-date-class'); // a complete <time> element

// In view code with no constructor to inject into:
echo AdminDate::full($record->createdAt, __('Never', 'corex'))->toHtml();
```

In React:

```jsx
import CorexTime, { CorexRelativeTime } from '../admin/components/CorexTime.js';

<CorexTime value={ record.created_at } absent={ __( 'Not recorded', 'corex' ) } />
<CorexRelativeTime value={ item.occurred_at } />
```

`CorexTime` reads `window.corexDateTime`, which is localized onto `corex-runtime` once per CoreX
screen by `CorexAdminAssets`. Every CoreX admin bundle already depends on `corex-runtime`, so it is
always present. A component test needs it installed —
`tests/Support/adminDateTimeConfig.js` provides `installDateTimeConfig()`.

The browser never translates a date itself. It receives already-translated month names, meridiem
markers and format patterns from PHP and composes with those, because `Intl` reads CLDR while
WordPress reads its own translation files, and in Arabic the two disagree (`أغسطس` against `آب`).
One dictionary, exported across one boundary.

`tests/Fixtures/datetime-parity.json` holds the instants both test suites format and compare against
the same expected strings. Changing either implementation alone turns the other red.
