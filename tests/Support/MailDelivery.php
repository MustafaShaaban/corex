<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

/**
 * Decides, for a test's own process, what the install does with an email.
 *
 * What happens to an email is decided from three settings: the environment, the delivery
 * provider and whether live delivery is on. The integration suite runs on a developer's real
 * install, and one of those is set to deliver, through an SMTP plugin with a real mailbox
 * behind it: a test that sent on the install's own settings handed its message to that mailbox
 * (2026-10-11, four of them). CI's site refuses every email instead, so the same test proved
 * nothing there.
 *
 * A test that sends says which it wants. Nothing is stored: each setting is answered through
 * its `pre_option_` filter, and the filters are handed back to be removed.
 */
final class MailDelivery
{
    /** A development site: an email is kept in Email Studio and reaches nobody. */
    public const CAPTURE = 'capture';

    /** A production site with a provider chosen and live delivery off: an email is refused. */
    public const REFUSE = 'refuse';

    private const SETTINGS = [
        self::CAPTURE => ['corex_app_env' => 'development'],
        self::REFUSE => [
            'corex_app_env' => 'production',
            'corex_mail_provider' => 'wp-mail',
            'corex_mail_live_delivery' => '0',
        ],
    ];

    /**
     * @return list<array{0:string,1:callable}> Each filter with its hook, for {@see stop()}.
     */
    public static function answerAs(string $mode): array
    {
        $filters = [];
        foreach (self::SETTINGS[$mode] as $option => $value) {
            $filter = static fn (): string => $value;
            add_filter('pre_option_' . $option, $filter);
            $filters[] = ['pre_option_' . $option, $filter];
        }

        return $filters;
    }

    /**
     * @param list<array{0:string,1:callable}> $filters What {@see answerAs()} returned.
     */
    public static function stop(array $filters): void
    {
        foreach ($filters as [$hook, $filter]) {
            remove_filter($hook, $filter);
        }
    }
}
