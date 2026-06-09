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
 * The HR notification when a new application arrives.
 */
final class NewApplicationTemplate extends EmailTemplate
{
    public function name(): string
    {
        return 'careers-new-application';
    }

    public function subject(MailContext $context): string
    {
        return __('New job application', 'corex');
    }

    public function body(MailContext $context): string
    {
        return '<p>' . esc_html__('A new application was received from:', 'corex') . ' {{ name }}</p>';
    }
}
