<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers\Templates;

defined('ABSPATH') || exit;

use Corex\Email\Template\EmailTemplate;
use Corex\Email\Template\MailContext;

/**
 * The applicant's confirmation that their application was received.
 */
final class ApplicationReceivedTemplate extends EmailTemplate
{
    public function name(): string
    {
        return 'careers-application-received';
    }

    public function subject(MailContext $context): string
    {
        return __('We received your application', 'corex');
    }

    public function body(MailContext $context): string
    {
        return '<p>' . esc_html__('Hi', 'corex') . ' {{ name }},</p>'
            . '<p>' . esc_html__('Thank you for applying — our team will review your application and be in touch.', 'corex') . '</p>';
    }
}
