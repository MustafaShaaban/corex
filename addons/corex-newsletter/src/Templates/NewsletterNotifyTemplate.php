<?php

/**
 * @package Corex\Newsletter
 */

declare(strict_types=1);

namespace Corex\Newsletter\Templates;

defined('ABSPATH') || exit;

use Corex\Email\Template\EmailTemplate;
use Corex\Email\Template\MailContext;

/**
 * The on-publish notification email: the new article + a one-click unsubscribe link.
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
        $article     = esc_url($context->get('url'));
        $unsubscribe = esc_url(add_query_arg(
            ['corex_newsletter' => 'unsubscribe', 'token' => $context->get('unsubscribe_token')],
            home_url('/')
        ));

        return '<p>' . esc_html__('A new article was published:', 'corex') . '</p>'
            . '<p><a href="' . $article . '">{{ title }}</a></p>'
            . '<p style="font-size:smaller"><a href="' . $unsubscribe . '">' . esc_html__('Unsubscribe', 'corex') . '</a></p>';
    }
}
