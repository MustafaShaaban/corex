<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Queue;

defined('ABSPATH') || exit;

use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Support\Uuid;

/**
 * The WP-Cron dispatcher: what defers a send on a site without Action Scheduler (#271).
 *
 * A queued request is kept in an option of its own, not autoloaded, and a single cron event
 * carries only its id. The cron array is read on every request and rewritten on every
 * schedule, so a request's body and context do not belong in it.
 *
 * It suits the handful of messages a form or a booking sends. Each queued message costs two
 * writes, and one run of WP-Cron works through whatever is due until PHP's time limit, leaving
 * the rest for the next run. A site that sends to a long list wants Action Scheduler, which
 * CoreX Mail prefers whenever it is installed.
 */
final class CronMailDispatcher implements MailQueueDispatcher
{
    private const OPTION_PREFIX = 'corex_mail_queued_';

    public function __construct(private readonly Mailer $immediate)
    {
    }

    public function available(): bool
    {
        return function_exists('wp_schedule_single_event');
    }

    public function name(): string
    {
        return 'wp-cron';
    }

    public function enqueue(MailRequest $request): void
    {
        if (! $this->schedule(Uuid::v4(), MailRequestPayload::toArray($request))) {
            // Nothing is scheduled, so nothing would ever send it.
            $this->immediate->send($request);

            return;
        }

        // WordPress looks for due events when a request starts. Without this the message
        // would wait for the site's next visitor.
        add_action('shutdown', 'wp_cron');
    }

    public function handle(array $payload): void
    {
        $option = self::OPTION_PREFIX . (string) ($payload['queued'] ?? '');
        $stored = get_option($option);

        if (! is_array($stored)) {
            return;
        }

        // Removed before it is sent: a send that stops PHP is not repeated by a later run.
        delete_option($option);
        $this->immediate->send(MailRequestPayload::fromArray($stored));
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function schedule(string $id, array $payload): bool
    {
        $option = self::OPTION_PREFIX . $id;

        if (! add_option($option, $payload, '', false)) {
            return false;
        }

        if (wp_schedule_single_event(time(), self::HOOK, [['queued' => $id]]) === true) {
            return true;
        }

        delete_option($option);

        return false;
    }
}
