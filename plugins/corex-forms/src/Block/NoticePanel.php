<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowNotice;

/**
 * Spec 122's notice panel, drawn for every form block: a title, a message, a line saying when (spec
 * 124, FR-021), and another way to get in touch, each only when the notice has it.
 *
 * It is no live region: the page says it once, as it loads. The runtime draws the same panel from a
 * refusal that arrives after the page was loaded (`showClosedNotice()` in corex-runtime.js).
 */
final readonly class NoticePanel
{
    /** @param string $modifier the panel's kind, such as `test`; '' for spec 122's own */
    public function render(FlowNotice $notice, string $modifier = ''): string
    {
        $link = $notice->link();

        return sprintf(
            '<div class="corex-flow-notice%1$s"><p class="corex-flow-notice__title">%2$s</p>'
            . '<p class="corex-flow-notice__message">%3$s</p>%4$s%5$s</div>',
            $modifier === '' ? '' : ' corex-flow-notice--' . sanitize_html_class($modifier),
            esc_html($notice->title),
            esc_html($notice->message),
            $notice->when === '' ? '' : sprintf('<p class="corex-flow-notice__when">%s</p>', esc_html($notice->when)),
            $link['url'] === '' ? '' : sprintf(
                '<p class="corex-flow-notice__alternative"><a href="%1$s">%2$s</a></p>',
                esc_url($link['url'], ['http', 'https', 'mailto']),
                esc_html($link['label']),
            ),
        );
    }
}
