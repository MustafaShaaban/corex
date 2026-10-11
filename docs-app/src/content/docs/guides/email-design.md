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
| **Email accent colour** | The edge along the top of the email's card. Six-digit hex, such as `#0B1F3B`. | CoreX's brass |
| **Email logo** | The mark at the top of the email. Use an image at least 360 pixels wide; it is shown at up to 180. | The site's logo, then its icon, then its name |
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
where the filter runs, and CoreX then draws the email from the brand it had before the filter.
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
| `$brand->direction`, `->language` | `ltr` or `rtl`, and a language tag for the document. |
| `$brand->footer` | The footer line the site set, or `''`. |
| `$brand->light`, `->dark` | The colours: `canvas`, `surface`, `border`, `text`, `muted`, `accent`, `link`. |

A design may add attributes to the message's tags, to style them. It must not leave the message
out, shorten it or reorder it.

### When a design fails

A site's design runs at the moment a customer is being answered. If it throws, answers nothing,
or answers a document that does not hold the message, CoreX sends the email in the standard
design and tells everybody who manages email, in the notification centre, which design failed
and how. The reply is not stopped and is never sent without its words.

"Holds the message" means its words, whole and in order, with spacing ignored.

### What mail clients lay out

Tables and inline styles, and little else. No style sheet is read for the message, no custom
property, no flexbox. Keep widths fluid up to 600 pixels. Use `left` and `right` chosen from
`$brand->direction`: Outlook does not read `start`. The generated file does all of this.

### Seeing it

On a development site a reply is kept in **CoreX → Email Studio** and delivered to nobody:
send one from a submission and open the capture.

## The plain-text version

Every reply is sent with its words as plain text beside the HTML. It is built from the message,
not from the design, so a site's design does not change it; its last line is the site's footer
line, or the standard one.
