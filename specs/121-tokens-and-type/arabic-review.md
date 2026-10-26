# The Arabic review (spec 121, US-NFR-02, TC-NFR-02-07)

The Arabic catalogue (`plugins/corex-core/languages/corex-ar.po`) was drafted in this release from the translation
template, following `plugins/corex-core/languages/glossary-ar.md`. Decision NFR-D3 asks that a native Arabic speaker
the owner names reviews it before R2.1 ships. This file is where that review is recorded.

**Status: owed.** No reviewer is named yet.

## What the reviewer reads

1. The screenshots of the Arabic proof: the `quality-findings` artifact of the pull request's or the release
   candidate's **Quality checks** job, folder `arabic/`. Every CoreX admin screen and tab, every theme template with
   the Company kit and the Contact form with its errors, each at 375 and 1280px, light and dark.
2. The catalogue itself, for strings the screenshots do not show (emails, rare errors). Search it by English text.

To see one screen live on a scratch site: copy `tests/e2e/fixtures/corex-e2e-arabic.php` into
`wp-content/mu-plugins/` and set the cookie `corex_e2e_locale=ar` in the browser.

## The checklist

For each screenshot, and for a sample of at least 50 catalogue entries chosen across the add-ons:

| Check | What passes |
|---|---|
| Letters are joined | No Arabic word is drawn as separate letters; the face is IBM Plex Sans Arabic, not a fallback |
| Punctuation side | The Arabic comma ، semicolon ؛ and question mark ؟ are used, and full stops and colons sit at the left end of a right-to-left line |
| Numerals | Western digits (0 to 9), as the glossary says; a number keeps its place in the sentence |
| Mixed text | An address, a file name, a version or a product name inside an Arabic sentence reads in the right order |
| Line spacing | Running text is not cramped (line height 1.8; headings 1.3) |
| Truncation | No label, button or table heading is cut off or wraps into another control at 375px |
| Terms | Each term is the glossary's; a term not in the glossary is added to it |
| Meaning and register | Clear Modern Standard Arabic, addressed to the site owner as أنت; no literal or awkward rendering |

## Record

| Date | Reviewer | Release candidate | Screens read | Entries sampled | Result | Corrections (commit) |
|---|---|---|---|---|---|---|
| | | | | | | |

When the review passes, `PROJECT-STATUS.md`'s item "Arabic typography has layout proof, not type proof" is
replaced by a line that cites this record.
