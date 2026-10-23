<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail\Capture;

defined('ABSPATH') || exit;

/**
 * Names the emails WordPress builds itself, for the Email Studio log: each is made by a filter
 * WordPress runs just before it calls `wp_mail()`, so the filter leaves the name for that email
 * (spec 113, research R4). The filters' values pass through unchanged.
 *
 * The comment emails are not named: WordPress builds their text once and then mails each
 * recipient, so a name left for the first would be wrong for the rest. They are "Other: wp_mail".
 */
final class WordPressMailNames
{
    public function __construct(private readonly MailPath $paths)
    {
    }

    public function register(): void
    {
        foreach ($this->filters() as $filter => $name) {
            add_filter($filter, function (mixed $value) use ($name): mixed {
                $this->paths->next($name());

                return $value;
            }, PHP_INT_MAX);
        }
    }

    /** @return array<string,callable(): string> */
    private function filters(): array
    {
        return [
            'retrieve_password_notification_email'  => static fn (): string => __('WordPress: password reset', 'corex'),
            'wp_new_user_notification_email'        => static fn (): string => __('WordPress: new user', 'corex'),
            'wp_new_user_notification_email_admin'  => static fn (): string => __('WordPress: new user (administrator)', 'corex'),
            'wp_password_change_notification_email' => static fn (): string => __('WordPress: password changed', 'corex'),
            'password_change_email'                 => static fn (): string => __('WordPress: account change', 'corex'),
            'email_change_email'                    => static fn (): string => __('WordPress: account change', 'corex'),
            'new_admin_email_content'               => static fn (): string => __('WordPress: site address change', 'corex'),
            'auto_core_update_email'                => static fn (): string => __('WordPress: update notice', 'corex'),
        ];
    }
}
