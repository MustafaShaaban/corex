---
title: A site's own email design
description: Give a client site its own colours, logo and footer in the emails CoreX sends for it, or its own template, from the site's own code.
---

CoreX draws a reply sent from **Submissions** in its standard email design: the site's logo or
name, the message on a card, a footer naming the site. A client site can change what that design
is drawn from, or replace the design, **without editing a framework file**. A CoreX update does
not undo either.

Only replies are drawn this way so far. Form notifications, routed emails and Email Studio's
templates still use their own layout.

## Only the colours, the logo and the footer

No code. In **CoreX → Settings → Mail**:

| Setting | What it changes | Left blank |
|---|---|---|
| **Email accent colour** | The edge along the top of the email's card, in light and dark mail clients alike: choose one that shows on both white and near-black. Six-digit hex, such as `#0B1F3B`. | CoreX's brass |
| **Email logo** | The mark at the top of the email. A whole web address (`https://…`) of a PNG or JPEG image; anything else, a vector image included, is passed over. It is drawn inside a box of 180 by 48 pixels with its proportions kept, so a square, a wide or a tall logo all fit; an image about twice that size stays sharp on a dense screen. A square logo has the site's name beside it; one at least twice as wide as it is tall is read as the name and stands alone. Choose an image from this site's media library: CoreX reads its size and writes the fitted one into the email, which is what Outlook on Windows goes by. For an image at an address elsewhere CoreX knows no size, and Outlook on Windows draws it as large as it is. | The site's logo, then its icon, then its name |
| **Email footer line** | The line under the card. | "This message was sent by" and the site's name, linked to the site |

A colour that is not six-digit hex is not saved: the field keeps the value it had.

From code, for values a setting does not reach (the text, link and background colours), a site's
plugin filters the brand:

```php
use Corex\Email\Design\EmailBrand;

add_filter('corex_email_brand', static function (EmailBrand $brand): EmailBrand {
    return $brand->withPalettes(
        $brand->light->with(['accent' => '#0b1f3b', 'link' => '#0b1f3b']),
        $brand->dark->with(['accent' => '#8fb4e8', 'link' => '#8fb4e8']),
    );
});
```

Each colour is written into a `style` attribute, so it has to be `#rrggbb`; anything else throws
where the filter runs. CoreX then draws the email from the brand the settings gave, and tells
the people who manage email that the filter failed and what it said.
Keep a contrast of 4.5 to 1 between `text`, `muted` and `link` and the two grounds, `surface`
and `canvas`: the standard palettes do, and nothing checks yours.

## Its own template

1. From the site's repository:

   ```bash
   wp corex make:email-design Acme
   ```

   This writes `src/Mail/AcmeEmailDesign.php` into the site's plugin: a working copy of the
   standard design, with a comment at its top saying what it is handed.

2. Register it once, in the `boot()` of the site plugin's service provider:

   ```php
   add_filter('corex_email_design', static fn () => new \AcmeSite\Mail\AcmeEmailDesign());
   ```

3. Change the file. It is the site's: nothing in CoreX overwrites it.

### What a design is handed

`render(EmailContent $content, EmailBrand $brand): string` answers a whole HTML document.

| | |
|---|---|
| `$content->subject` | Plain text. Escape it before printing it. |
| `$content->bodyHtml` | The message, already cleaned: only `<p>`, `<br>`, `<strong>`, `<b>`, `<em>`, `<i>`, `<ul>`, `<ol>`, `<li>` and `<a href>`. Print it as it is. |
| `$content->recipient` | The address the email is for. |
| `$brand->name`, `->homeUrl`, `->logo` | The site's name, its address, and an absolute address of its mark, or `''`. |
| `$brand->logoBox()` | The width and height to draw the logo at, inside 180 by 48 with its proportions kept, or `null` where the logo's own size is not known (an address outside this site's uploads). Write them as the image's `width` and `height`: Outlook on Windows reads those and not `max-height`. |
| `$brand->logoIsItsName()` | Whether the logo is at least twice as wide as it is tall, and so read as the name written out. The standard design writes the site's name as text beside a logo that is not, so that the name is there when a mail client shows no images. |
| `$brand->withLogoSize($width, $height)` | The brand with its logo's own size said, in pixels. Call it when a `corex_email_brand` filter of yours names a logo CoreX cannot measure. |
| `$brand->direction`, `->language` | `ltr` or `rtl`, and a language tag for the document. |
| `$brand->footer` | The footer line the site set, or `''`. |
| `$brand->light`, `->dark` | The colours: `canvas`, `surface`, `border`, `text`, `muted`, `accent`, `link`. |

A design may add attributes to the message's tags, to style them. It must print the message as
it was handed: not left out, shortened or reordered, and not rewritten either. Curling its
quotes, putting an address after a link or changing its case makes it another text, and CoreX
then sends the standard design.

### When a design fails

A site's design runs at the moment a customer is being answered. If it throws, answers nothing,
or answers a document that does not hold the message, CoreX sends the email in the standard
design and tells everybody who manages email, in the notification centre, which design failed
and how. The reply is not stopped and is never sent without its words.

"Holds the message" means its words, whole and in order, in what the document shows: its
title, style sheets and comments do not count, and spacing is ignored.

The filter itself is inside the same guard. If the line that registers the design throws (a
class renamed, a constructor that fails), the reply still goes, in the standard design.

A design that prints its markup and returns nothing is treated as answering nothing: return the
document. To build on CoreX's design, give your class a constructor that takes it, and wrap
the one the filter hands you:

```php
add_filter('corex_email_design', static fn ($standard) => new \AcmeSite\Mail\AcmeEmailDesign($standard));
```

### What mail clients lay out

Tables and inline styles, and little else. No style sheet is read for the message, no custom
property, no flexbox. Keep widths fluid up to 600 pixels. Align the cells `left` or `right`
from `$brand->direction`: Outlook does not read `start`. Give each paragraph `dir="auto"` and
`text-align:start`, which every client that is a browser reads, so that a line in another
language starts from its own side. The generated file does all of this, and has the dark
colours for a client that asks for them.

### Seeing it

On a development site a reply is kept in **CoreX → Email Studio** and delivered to nobody:
send one from a submission and open the capture.

## The plain-text version

Every reply is sent with its words as plain text beside the HTML. It is built from the message,
not from the design, so a site's design does not change it; its last line is the site's footer
line, or the standard one.
