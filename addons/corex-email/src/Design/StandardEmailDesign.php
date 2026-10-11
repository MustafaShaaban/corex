<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

/**
 * CoreX's standard email: the site's name or logo, the message on a card with the brand's edge,
 * and a footer naming the site (spec 106, FR-008 to FR-011).
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
            . sprintf(
                '<tr><td class="cx-text" style="padding:0 0 16px;text-align:%s;color:%s;font-family:%s;font-size:18px;font-weight:600;line-height:1.3">%s</td></tr>',
                $align,
                $light->text,
                $font,
                $this->header($brand),
            )
            . sprintf(
                '<tr><td class="cx-surface" style="padding:%1$s %1$s 8px;background:%2$s;border:1px solid %3$s;border-top:4px solid %4$s;border-radius:%5$s;text-align:%6$s">%7$s</td></tr>',
                EmailBrand::SPACE,
                $light->surface,
                $light->border,
                $light->accent,
                EmailBrand::RADIUS,
                $align,
                $this->message($content->bodyHtml, $light),
            )
            . sprintf(
                '<tr><td class="cx-muted" style="padding:16px 0 0;text-align:%s;color:%s;font-family:%s;font-size:12px;line-height:%s">%s</td></tr>',
                $align,
                $light->muted,
                $font,
                EmailBrand::LINE_HEIGHT,
                $this->footer($brand, $light),
            )
            . '</table></td></tr></table></body></html>';
    }

    /**
     * The site's logo, or its name. The logo's `alt` is the name, drawn in the header's own
     * type, so a client that shows no images shows the name in its place (FR-009).
     */
    private function header(EmailBrand $brand): string
    {
        $name = $this->escaped($brand->name);
        if ($brand->logo === '') {
            return $name;
        }

        return sprintf(
            '<img src="%s" alt="%s" style="display:block;max-width:180px;height:auto;border:0" />',
            $this->escaped($brand->logo),
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

        return strtr($bodyHtml, [
            '<p>' => sprintf('<p dir="auto" class="cx-text" style="margin:0 0 16px;%s">', $text),
            '<ul>' => sprintf('<ul style="margin:0 0 16px;padding:0 %s">', EmailBrand::SPACE),
            '<ol>' => sprintf('<ol style="margin:0 0 16px;padding:0 %s">', EmailBrand::SPACE),
            '<li>' => sprintf('<li dir="auto" class="cx-text" style="margin:0 0 6px;%s">', $text),
            '<a href=' => sprintf('<a class="cx-link" style="color:%s;text-decoration:underline" href=', $light->link),
        ]);
    }

    private function footer(EmailBrand $brand, EmailPalette $light): string
    {
        if ($brand->footer !== '') {
            return $this->escaped($brand->footer);
        }
        $site = sprintf(
            '<a class="cx-muted" style="color:%s;text-decoration:underline" href="%s">%s</a>',
            $light->muted,
            $this->escaped($brand->homeUrl),
            $this->escaped($brand->name),
        );

        /* translators: %s: the name of the site an email was sent from, as a link to it. */
        return sprintf($this->escaped(__('This message was sent by %s.', 'corex')), $site);
    }

    /** The dark palette, for a client that asks for it. */
    private function darkScheme(EmailPalette $dark): string
    {
        return sprintf(
            '<style>:root{color-scheme:light dark}'
            . '@media (prefers-color-scheme: dark){'
            . '.cx-canvas{background:%1$s !important}'
            . '.cx-surface{background:%2$s !important;border-color:%3$s !important;border-top-color:%4$s !important}'
            . '.cx-text{color:%5$s !important}'
            . '.cx-muted{color:%6$s !important}'
            . '.cx-link{color:%7$s !important}'
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

    /** For text and for an attribute's value alike: both quotes are escaped. */
    private function escaped(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
