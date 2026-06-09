<?php

/**
 * @package Corex\Bookings
 */

declare(strict_types=1);

namespace Corex\Bookings\Templates;

defined('ABSPATH') || exit;

use Corex\Email\Template\EmailTemplate;
use Corex\Email\Template\MailContext;

/**
 * Notifies the leader that someone requested a call.
 */
final class CallRequestLeaderTemplate extends EmailTemplate
{
    public function name(): string
    {
        return 'call-request-leader';
    }

    public function subject(MailContext $context): string
    {
        return __('New call request', 'corex');
    }

    public function body(MailContext $context): string
    {
        return '<p>' . esc_html__('Someone requested a call:', 'corex') . '</p>'
            . '<table role="presentation" cellpadding="6" style="text-align:start">'
            . '<tr><th style="text-align:start">' . esc_html__('Name', 'corex') . '</th><td>{{ name }}</td></tr>'
            . '<tr><th style="text-align:start">' . esc_html__('Phone', 'corex') . '</th><td>{{ phone }}</td></tr>'
            . '<tr><th style="text-align:start">' . esc_html__('Preferred time', 'corex') . '</th><td>{{ preferred_time }}</td></tr>'
            . '</table>';
    }
}
