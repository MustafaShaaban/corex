<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Queue;

defined('ABSPATH') || exit;

use Corex\Mail\MailRequest;

/**
 * Hands a mail request to a background queue, and delivers what it queued when the queue
 * runs. The interface keeps QueuedMailer free of any concrete scheduler, so the queue
 * decision is unit-testable.
 *
 * A dispatcher schedules its work on {@see self::HOOK}. CoreX Mail listens on that hook and
 * calls `handle()` on whichever dispatcher is bound, so a dispatcher a site binds in place
 * of the shipped ones is run by the same worker (#271).
 */
interface MailQueueDispatcher
{
    public const HOOK = 'corex_mail_send';

    /**
     * Whether a queue backend is actually available right now.
     */
    public function available(): bool;

    /**
     * The backend's name, as a delivery attempt records its provider.
     */
    public function name(): string;

    /**
     * Schedule the request for asynchronous delivery.
     */
    public function enqueue(MailRequest $request): void;

    /**
     * Deliver what one firing of the hook was scheduled with.
     *
     * @param array<string,mixed> $payload The first argument the hook fired with.
     */
    public function handle(array $payload): void;
}
