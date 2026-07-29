<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Support;

defined('ABSPATH') || exit;

use Corex\Email\Template\EmailTemplate;
use Corex\Email\Template\MailContext;

/**
 * The Guides support message, wearing the CoreX admin's face (spec 093).
 *
 * **This class extends a Corex Mail base class, so it must never be autoloaded on an install where
 * `addons/corex-email` is absent** — referencing it would fatal on the missing parent.
 * {@see \Corex\Guides\GuidesServiceProvider} guards its only construction with `class_exists()`,
 * and {@see SupportMailer} keeps a plain-text `wp_mail()` path for exactly that install
 * (Principle IX: no optional add-on is a hard dependency).
 *
 * Values arrive as `{{ support.* }}` placeholders and are merged **and escaped** by
 * {@see \Corex\Email\Template\TemplateRenderer}, so nothing a reader types can become live markup.
 * That is also why the message body is wrapped in a container with `white-space: pre-wrap` rather
 * than converted to `<br>`: the newlines somebody typed survive without this template ever
 * concatenating their text into HTML.
 *
 * Styling is inline and table-based because that is what mail clients support — no stylesheet, no
 * custom properties, no flexbox. The colours come from {@see SupportEmailPalette}, which explains
 * why they are literals at all.
 *
 * `Layout` still wraps the result: the 600px shell, the direction, and the site logo are its job.
 * This is the card inside it.
 */
final class SupportRequestTemplate extends EmailTemplate
{
    public const NAME = 'corex-guides-support';

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * Built from placeholders, not taken from the `MailRequest`.
     *
     * `TemplateRenderer` composes the subject from *this* method whenever a template is named — the
     * request's own `subject` is ignored on that path. Returning a literal here would therefore have
     * thrown away the site name and the category, which are the two things that make one of these
     * triageable in a shared inbox, and the loss would have been invisible: the email still arrives.
     *
     * The renderer merges subject placeholders **unescaped** (a subject is not markup), which is
     * correct and is why nothing here needs `esc_*`.
     */
    public function subject(MailContext $context): string
    {
        return sprintf(
            /* translators: 1: the site name, 2: what the message is about. */
            __('[%1$s] Guides support: %2$s', 'corex'),
            '{{ support.site_name }}',
            '{{ support.category }}',
        );
    }

    public function body(MailContext $context): string
    {
        return $this->intro()
            . $this->detailTable()
            . $this->messageCard()
            . $this->footer();
    }

    private function intro(): string
    {
        return sprintf(
            '<p style="margin:0 0 %1$s;color:%2$s;font-family:%3$s;font-size:16px;line-height:%4$s">%5$s</p>',
            SupportEmailPalette::SPACE,
            SupportEmailPalette::TEXT,
            SupportEmailPalette::FONT,
            SupportEmailPalette::LINE_HEIGHT,
            esc_html__('Somebody read the guides on your site and could not find what they needed.', 'corex'),
        );
    }

    /**
     * Who sent it, what it is about, and where from — the four facts that make a reply possible.
     *
     * A table rather than a definition list, because Outlook's rendering engine does not lay one
     * out. The same reason `ContactNotificationTemplate` uses one.
     */
    private function detailTable(): string
    {
        $rows = [
            __('About', 'corex')    => '{{ support.category }}',
            __('From', 'corex')     => '{{ support.sender }}',
            __('Reply to', 'corex') => '{{ support.reply_to }}',
            __('Site', 'corex')     => '{{ support.site }}',
        ];

        $markup = '';
        foreach ($rows as $label => $value) {
            $markup .= sprintf(
                '<tr>'
                . '<th style="padding:6px 16px 6px 0;text-align:start;vertical-align:top;'
                . 'color:%1$s;font-family:%2$s;font-size:13px;font-weight:600;white-space:nowrap">%3$s</th>'
                . '<td style="padding:6px 0;text-align:start;color:%4$s;font-family:%2$s;font-size:14px">%5$s</td>'
                . '</tr>',
                SupportEmailPalette::TEXT_MUTED,
                SupportEmailPalette::FONT,
                esc_html($label),
                SupportEmailPalette::TEXT,
                $value,
            );
        }

        return sprintf(
            '<table role="presentation" cellpadding="0" cellspacing="0" '
            . 'style="width:100%%;margin:0 0 %1$s;text-align:start">%2$s</table>',
            SupportEmailPalette::SPACE,
            $markup,
        );
    }

    /**
     * The message itself, in a card with the brass edge the admin uses for the same purpose.
     *
     * `white-space:pre-wrap` keeps the reader's own line breaks without this template turning their
     * text into markup — the renderer escapes the value, so a `<br>` would have to be built here
     * from text that is deliberately no longer trusted.
     */
    private function messageCard(): string
    {
        return sprintf(
            '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%%;margin:0 0 %1$s">'
            . '<tr><td style="padding:%1$s;background:%2$s;border:1px solid %3$s;'
            . 'border-inline-start:4px solid %4$s;border-radius:%5$s;text-align:start">'
            . '<p style="margin:0 0 8px;color:%6$s;font-family:%7$s;font-size:12px;'
            . 'font-weight:600;letter-spacing:.08em;text-transform:uppercase">%8$s</p>'
            . '<div style="margin:0;color:%9$s;font-family:%7$s;font-size:15px;line-height:%10$s;'
            . 'white-space:pre-wrap">{{ support.message }}</div>'
            . '</td></tr></table>',
            SupportEmailPalette::SPACE,
            SupportEmailPalette::SURFACE,
            SupportEmailPalette::BORDER,
            SupportEmailPalette::ACTION,
            SupportEmailPalette::RADIUS,
            SupportEmailPalette::TEXT_MUTED,
            SupportEmailPalette::FONT,
            esc_html__('Their message', 'corex'),
            SupportEmailPalette::TEXT,
            SupportEmailPalette::LINE_HEIGHT,
        );
    }

    private function footer(): string
    {
        return sprintf(
            '<p style="margin:0;padding-block-start:%1$s;border-block-start:1px solid %2$s;'
            . 'color:%3$s;font-family:%4$s;font-size:12px;line-height:%5$s;text-align:start">%6$s</p>',
            SupportEmailPalette::SPACE,
            SupportEmailPalette::BORDER,
            SupportEmailPalette::TEXT_MUTED,
            SupportEmailPalette::FONT,
            SupportEmailPalette::LINE_HEIGHT,
            esc_html__('Sent from the Guides screen in your CoreX admin. Reply to this email to answer directly.', 'corex'),
        );
    }
}
