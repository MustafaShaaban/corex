<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Boot;
use Corex\Mail\SubmissionEmailGateway;
use RuntimeException;

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

    /**
     * A production site with a provider and live delivery on: an email goes to `wp_mail()`, the
     * way a client site sends (spec 113 leaves production untouched). The transport is never
     * reached: a `pre_wp_mail` answer that comes before every other one stops it, and a test's
     * own `pre_wp_mail` listener still sees each email.
     */
    public const DELIVER = 'deliver';

    private const SETTINGS = [
        self::CAPTURE => ['corex_app_env' => 'development'],
        self::REFUSE => [
            'corex_app_env' => 'production',
            'corex_mail_provider' => 'wp-mail',
            'corex_mail_live_delivery' => '0',
        ],
        self::DELIVER => [
            'corex_app_env' => 'production',
            'corex_mail_provider' => 'wp-mail',
            'corex_mail_live_delivery' => '1',
        ],
    ];

    /**
     * @return list<array{0:string,1:callable,2?:int}> Each filter with its hook (and priority), for {@see stop()}.
     */
    public static function answerAs(string $mode): array
    {
        $filters = [];
        foreach (self::SETTINGS[$mode] as $option => $value) {
            $filter = static fn (): string => $value;
            add_filter('pre_option_' . $option, $filter);
            $filters[] = ['pre_option_' . $option, $filter];
        }

        if ($mode === self::DELIVER) {
            $stop = static fn (mixed $return): mixed => $return ?? true;
            add_filter('pre_wp_mail', $stop, PHP_INT_MIN);
            $filters[] = ['pre_wp_mail', $stop, PHP_INT_MIN];
        }

        // An install can fix these settings where an option does not reach: an `.env` file is
        // read before the options are. The answer is checked against the decision a send takes,
        // before any test has sent anything.
        $decided = Boot::app()->container()->make(SubmissionEmailGateway::class)->readiness()->mode;
        if ($decided !== $mode) {
            self::stop($filters);

            throw new RuntimeException(sprintf(
                'This install decides "%s" for an email whatever a test answers (asked for "%s"). No test may send on it.',
                $decided,
                $mode,
            ));
        }

        return $filters;
    }

    /**
     * @param list<array{0:string,1:callable,2?:int}> $filters What {@see answerAs()} returned.
     */
    public static function stop(array $filters): void
    {
        foreach ($filters as $entry) {
            remove_filter($entry[0], $entry[1], $entry[2] ?? 10);
        }
    }
}
