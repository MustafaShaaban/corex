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
 * The on-publish notification email: the new article, with a one-click unsubscribe link in its
 * footer.
 */
final class NewsletterNotifyTemplate extends EmailTemplate
{
    public function name(): string
    {
        return 'newsletter-notify';
    }

    public function subject(MailContext $context): string
    {
        return sprintf(
            /* translators: %s: article title */
            __('New article: %s', 'corex'),
            $context->get('title')
        );
    }

    public function body(MailContext $context): string
    {
        $article = esc_url($context->get('url'));

        // The unsubscribe link is in the frame's footer, with the others (spec 120).
        return '<p>' . esc_html__('A new article was published:', 'corex') . '</p>'
            . '<p><a href="' . $article . '">{{ title }}</a></p>';
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
