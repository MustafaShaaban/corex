<?php

/**
 * @package Corex\Newsletter
 */

declare(strict_types=1);

namespace Corex\Newsletter\Templates;

defined('ABSPATH') || exit;

use Corex\Email\Template\EmailTemplate;
use Corex\Email\Design\EmailKind;
use Corex\Email\Template\MailContext;

/**
 * The double opt-in confirmation email: a signed confirm link.
 */
final class NewsletterConfirmTemplate extends EmailTemplate
{
    public function name(): string
    {
        return 'newsletter-confirm';
    }

    public function subject(MailContext $context): string
    {
        return __('Please confirm your subscription', 'corex');
    }

    public function body(MailContext $context): string
    {
        $url = esc_url(NewsletterLinks::confirm($context->get('confirm_token')));

        return '<p>' . esc_html__('Thanks for subscribing. Please confirm your email to start receiving updates:', 'corex') . '</p>'
            . '<p><a href="' . $url . '">' . esc_html__('Confirm subscription', 'corex') . '</a></p>'
            . '<p>' . esc_html__('If you did not sign up, you can ignore this email: no subscription is made.', 'corex') . '</p>';
    }

    public function links(MailContext $context): array
    {
        return NewsletterLinks::footer($context->get('unsubscribe_token'), $context->get('email'));
    }

    /** What the frame's footer says about why the person receives it (spec 120). */
    public function kind(): string
    {
        return EmailKind::NEWSLETTER;
    }
}
