<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

/**
 * CoreX's standard email: a card with the brand's edge that opens with the site's logo or name
 * and holds the message, and a footer naming the site (spec 106, FR-008 to FR-011, FR-032).
 *
 * The header is a band of the card and not a row above it: above it, on the canvas, a logo
 * read as something that had come loose from the email.
 *
 * Tables and inline styles, because that is what mail clients lay out: no style sheet is read
 * for the message, no custom property, no flexbox. The widths are fluid up to 600 pixels, which
 * is the measure mail clients are built around, so the same document holds on a phone.
 *
 * Light colours are written inline, for the client that honours nothing else. A client that
 * honours `prefers-color-scheme` is given the dark palette by class, in the one style block.
 * Physical `left` and `right` are used and chosen from the site's direction: Outlook's engine
 * does not read `start`.
 */
final class StandardEmailDesign implements EmailDesign
{
    public function render(EmailContent $content, EmailBrand $brand): string
    {
        $light = $brand->light;
        $align = $brand->direction === 'rtl' ? 'right' : 'left';
        $font = EmailBrand::FONT;

        return '<!DOCTYPE html>'
            . sprintf('<html lang="%s" dir="%s">', $this->escaped($brand->language), $brand->direction)
            . '<head><meta charset="utf-8" />'
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />'
            . '<meta name="color-scheme" content="light dark" />'
            . '<meta name="supported-color-schemes" content="light dark" />'
            . '<title>' . $this->escaped($content->subject) . '</title>'
            . $this->darkScheme($brand->dark)
            . '</head>'
            . sprintf('<body class="cx-canvas" style="margin:0;padding:0;background:%s">', $light->canvas)
            . sprintf(
                '<table role="presentation" class="cx-canvas" width="100%%" cellpadding="0" cellspacing="0" style="background:%s">'
                . '<tr><td align="center" style="padding:%s 12px">',
                $light->canvas,
                EmailBrand::SPACE,
            )
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px">'
            // The card is two bands of one box. Each side of each band is written singly:
            // Outlook's engine reads a border taken back (`border-top:0`) less surely than
            // one never given.
            . sprintf(
                '<tr><td class="cx-surface cx-band cx-text" style="padding:16px %1$s;background:%2$s;border-top:4px solid %4$s;border-left:1px solid %3$s;border-right:1px solid %3$s;border-bottom:1px solid %3$s;border-radius:%5$s %5$s 0 0;text-align:%6$s;%7$s">%8$s</td></tr>',
                EmailBrand::SPACE,
                $light->surface,
                $light->border,
                $light->accent,
                EmailBrand::RADIUS,
                $align,
                $this->headerType($light),
                $this->header($brand, $light, $align),
            )
            . sprintf(
                // The cell carries the type too: a template's table or line outside a paragraph
                // reads in it, and not in the mail client's own (spec 120).
                '<tr><td class="cx-surface cx-text" style="padding:%1$s %1$s 8px;background:%2$s;border-left:1px solid %3$s;border-right:1px solid %3$s;border-bottom:1px solid %3$s;border-radius:0 0 %4$s %4$s;text-align:%5$s;color:%7$s;font-family:%8$s;font-size:16px;line-height:%9$s">%6$s</td></tr>',
                EmailBrand::SPACE,
                $light->surface,
                $light->border,
                EmailBrand::RADIUS,
                $align,
                $this->message($content->bodyHtml, $light),
                $light->text,
                $font,
                EmailBrand::LINE_HEIGHT,
            )
            . sprintf(
                '<tr><td class="cx-muted" style="padding:16px 0 0;text-align:%s;color:%s;font-family:%s;font-size:12px;line-height:%s">%s</td></tr>',
                $align,
                $light->muted,
                $font,
                EmailBrand::LINE_HEIGHT,
                $this->footer($brand, $light) . $this->reason($content, $brand) . $this->links($content, $light),
            )
            . '</table></td></tr></table></body></html>';
    }

    /** The header's type, written on every cell that holds its words. */
    private function headerType(EmailPalette $light): string
    {
        return sprintf('color:%s;font-family:%s;font-size:18px;font-weight:600;line-height:1.3', $light->text, EmailBrand::FONT);
    }

    /**
     * What the card opens with: the site's name, its logo, or its mark and its name (FR-009,
     * FR-033).
     *
     * A logo is held to a box of 180 by 48. Where its real size is known it is written at the
     * width that fits, as the image's own `width` and `height` and as a width in its style,
     * with its height left to follow: Outlook on Windows reads the attributes and not a
     * `max-height`, and a client that shows no images draws the name that stands in for the
     * logo across that width in as many lines as it needs, where a height would cut it off.
     *
     * A logo that is the name written out stands alone, with the name as its `alt`. A mark
     * has the name beside it as text, and no `alt`: its box is 48 pixels wide, which holds
     * no name, and a mail client that blocks images, as Outlook does for a sender it does
     * not know, would have shown a customer an email from nobody.
     *
     * A logo whose size is not known is limited by its style alone and written with no
     * attributes: a height given to an image smaller than it would enlarge it. A client that
     * reads no style draws that one at its own size, which is what the Mail settings say.
     */
    private function header(EmailBrand $brand, EmailPalette $light, string $align): string
    {
        $name = $this->escaped($brand->name);
        $logo = $this->address($brand->logo);
        if ($logo === '') {
            return $name;
        }
        $box = $brand->logoBox();
        if ($box === null) {
            return sprintf(
                '<img src="%s" alt="%s" style="display:inline-block;vertical-align:middle;width:auto;height:auto;max-width:%dpx;max-height:%dpx;border:0" />',
                $logo,
                $name,
                EmailBrand::LOGO_WIDTH,
                EmailBrand::LOGO_HEIGHT,
            );
        }
        $image = sprintf(
            '<img src="%s" alt="%s" width="%d" height="%d" style="display:inline-block;vertical-align:middle;width:%dpx;max-width:100%%;height:auto;border:0" />',
            $logo,
            $brand->logoIsItsName() ? $name : '',
            $box[0],
            $box[1],
            $box[0],
        );
        if ($brand->logoIsItsName()) {
            return $image;
        }

        // Two cells, because a mail client gives an image no margin: the space between the
        // mark and the name is the first cell's padding, on the side the name is on.
        return sprintf(
            '<table role="presentation" dir="%1$s" align="%2$s" cellpadding="0" cellspacing="0"><tr>'
            . '<td style="padding-%3$s:12px;vertical-align:middle">%4$s</td>'
            . '<td class="cx-text" style="vertical-align:middle;text-align:%2$s;%5$s">%6$s</td>'
            . '</tr></table>',
            $brand->direction,
            $align,
            $align === 'left' ? 'right' : 'left',
            $image,
            $this->headerType($light),
            $name,
        );
    }

    /**
     * The message, with the styles its elements need written on each of them.
     *
     * The message arrives reduced: its tags carry no attribute but a link's address, so each
     * can be matched as written. `dir="auto"` lets an Arabic reply from an English site read
     * right to left, paragraph by paragraph, in every client that is a browser.
     */
    private function message(string $bodyHtml, EmailPalette $light): string
    {
        $text = sprintf('color:%s;font-family:%s;font-size:16px;line-height:%s', $light->text, EmailBrand::FONT, EmailBrand::LINE_HEIGHT);

        $heading = sprintf('color:%s;font-family:%s;font-weight:700;line-height:1.3', $light->text, EmailBrand::FONT);

        return strtr($bodyHtml, [
            // A template's heading and button (spec 120). The button is the text's colours
            // turned round, which keeps its contrast whatever the brand's accent is.
            '<h1>' => sprintf('<h1 dir="auto" class="cx-text" style="margin:0 0 16px;font-size:24px;text-align:start;%s">', $heading),
            '<h2>' => sprintf('<h2 dir="auto" class="cx-text" style="margin:0 0 12px;font-size:19px;text-align:start;%s">', $heading),
            '<a class="button" href=' => sprintf(
                '<a class="cx-button" style="display:inline-block;padding:12px 24px;border-radius:8px;background:%s;color:%s;font-family:%s;font-size:16px;font-weight:600;line-height:1.3;text-decoration:none" href=',
                $light->text,
                $light->surface,
                EmailBrand::FONT,
            ),
            '<p>' => sprintf('<p dir="auto" class="cx-text" style="margin:0 0 16px;text-align:start;%s">', $text),
            '<ul>' => sprintf('<ul style="margin:0 0 16px;padding:0 %s">', EmailBrand::SPACE),
            '<ol>' => sprintf('<ol style="margin:0 0 16px;padding:0 %s">', EmailBrand::SPACE),
            '<li>' => sprintf('<li dir="auto" class="cx-text" style="margin:0 0 6px;text-align:start;%s">', $text),
            '<a href=' => sprintf('<a class="cx-link" style="color:%s;text-decoration:underline" href=', $light->link),
        ]);
    }

    private function footer(EmailBrand $brand, EmailPalette $light): string
    {
        if ($brand->footer !== '') {
            return $this->escaped($brand->footer);
        }
        $home = $this->address($brand->homeUrl);
        $site = $home === ''
            ? $this->escaped($brand->name)
            : sprintf(
                '<a class="cx-muted" style="color:%s;text-decoration:underline" href="%s">%s</a>',
                $light->muted,
                $home,
                $this->escaped($brand->name),
            );

        /* translators: %s: the name of the site an email was sent from, as a link to it. */
        return sprintf($this->escaped(__('This message was sent by %s.', 'corex')), $site);
    }

    /** The line that says why the person receives the email, on a line of its own (spec 120). */
    private function reason(EmailContent $content, EmailBrand $brand): string
    {
        $reason = EmailKind::reason($content->kind, $brand->name);

        return $reason === '' ? '' : '<br />' . $this->escaped($reason);
    }

    /**
     * What the email links to from its footer, on one line, the links apart: the privacy page,
     * an unsubscribe link (spec 120).
     */
    private function links(EmailContent $content, EmailPalette $light): string
    {
        $links = [];
        foreach ($content->links as $link) {
            $address = $this->address($link['url']);
            if ($address !== '') {
                $links[] = sprintf(
                    '<a class="cx-muted" style="color:%s;text-decoration:underline" href="%s">%s</a>',
                    $light->muted,
                    $address,
                    $this->escaped($link['label']),
                );
            }
        }

        return $links === [] ? '' : '<br />' . implode(' &middot; ', $links);
    }

    /** The dark palette, for a client that asks for it. */
    private function darkScheme(EmailPalette $dark): string
    {
        return sprintf(
            '<style>:root{color-scheme:light dark}'
            . '@media (prefers-color-scheme: dark){'
            . '.cx-canvas{background:%1$s !important}'
            . '.cx-surface{background:%2$s !important;border-color:%3$s !important}'
            . '.cx-band{border-top-color:%4$s !important}'
            . '.cx-text{color:%5$s !important}'
            . '.cx-muted{color:%6$s !important}'
            . '.cx-link{color:%7$s !important}'
            . '.cx-button{background:%5$s !important;color:%2$s !important}'
            . '}</style>',
            $dark->canvas,
            $dark->surface,
            $dark->border,
            $dark->accent,
            $dark->text,
            $dark->muted,
            $dark->link,
        );
    }

    /**
     * A web address, escaped for an attribute, or '' for anything that is not one: what a
     * design links to and loads is the web, whatever it was handed.
     */
    private function address(string $value): string
    {
        return preg_match('#^https?://#i', $value) === 1 ? $this->escaped($value) : '';
    }

    /** For text and for an attribute's value alike: both quotes are escaped. */
    private function escaped(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
