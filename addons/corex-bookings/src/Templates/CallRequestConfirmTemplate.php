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
 * Confirms to the visitor that their call request was received.
 */
final class CallRequestConfirmTemplate extends EmailTemplate
{
    public function name(): string
    {
        return 'call-request-confirm';
    }

    public function subject(MailContext $context): string
    {
        return __('Your call request was received', 'corex');
    }

    public function body(MailContext $context): string
    {
        return '<p>' . esc_html__('Hi', 'corex') . ' {{ name }},</p>'
            . '<p>' . esc_html__('Thanks — your request to speak with {{ leader }} was received. We will be in touch to arrange a time.', 'corex') . '</p>';
    }
}
