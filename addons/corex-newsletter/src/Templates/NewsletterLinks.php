<?php

/**
 * @package Corex\Newsletter
 */

declare(strict_types=1);

namespace Corex\Newsletter\Templates;

defined('ABSPATH') || exit;

/**
 * The links every newsletter email carries in its footer (spec 120): one to unsubscribe and,
 * where the site gives one, one to manage preferences.
 *
 * The add-on has no preferences page, so it links to none of its own. A site that has one gives
 * its address through the filter `corex_newsletter_preferences_url`:
 *
 *     add_filter('corex_newsletter_preferences_url', fn (string $url, string $email) => home_url('/newsletter/'), 10, 2);
 */
final class NewsletterLinks
{
    public const PREFERENCES_FILTER = 'corex_newsletter_preferences_url';

    /** The signed address that unsubscribes the person this token was made for. */
    public static function unsubscribe(string $token): string
    {
        return (string) add_query_arg(
            ['corex_newsletter' => 'unsubscribe', 'token' => $token],
            home_url('/'),
        );
    }

    /** The site's preferences page for this address, or '' where it has none. */
    public static function preferences(string $email): string
    {
        /**
         * The address of this site's newsletter preferences page, or '' for none.
         *
         * @param string $url   '' unless another filter gave one.
         * @param string $email The subscriber's address.
         */
        $url = apply_filters(self::PREFERENCES_FILTER, '', $email);

        return is_string($url) && preg_match('#^https?://#i', $url) === 1 ? $url : '';
    }

    /**
     * The footer's links for one subscriber.
     *
     * @return list<array{label:string,url:string}>
     */
    public static function footer(string $unsubscribeToken, string $email): array
    {
        $links = [];
        if ($unsubscribeToken !== '') {
            $links[] = ['label' => __('Unsubscribe', 'corex'), 'url' => self::unsubscribe($unsubscribeToken)];
        }
        $preferences = self::preferences($email);
        if ($preferences !== '') {
            $links[] = ['label' => __('Manage preferences', 'corex'), 'url' => $preferences];
        }

        return $links;
    }
}
