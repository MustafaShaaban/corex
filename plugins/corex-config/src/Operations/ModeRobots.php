<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * Keeps search engines out of a development or staging site (spec 124, US-OPS-01, OPS-D2).
 *
 * In Development and Staging every front-end page carries `noindex`, in the page through WordPress's
 * own robots meta and in an `X-Robots-Tag` header for whatever is not a page. The Reading setting is
 * never changed, so switching to Production removes the noindex with no other step and the owner's
 * own choice in Settings > Reading is what the live site keeps. Every other mode adds nothing.
 */
final class ModeRobots
{
    private const KEPT_OUT = [OperationsMode::DEVELOPMENT, OperationsMode::STAGING];

    public function __construct(private readonly OperationsModeStore $store)
    {
    }

    /** Whether this mode keeps search engines out. */
    public static function keepsOut(string $mode): bool
    {
        return in_array($mode, self::KEPT_OUT, true);
    }

    public function register(): void
    {
        add_filter('wp_robots', [$this, 'robots'], 20);
        add_action('send_headers', [$this, 'header']);
    }

    /**
     * @param array<string,bool|string> $robots
     *
     * @return array<string,bool|string>
     */
    public function robots(array $robots): array
    {
        if (! self::keepsOut($this->store->current())) {
            return $robots;
        }

        return ['noindex' => true] + $robots;
    }

    public function header(): void
    {
        if (headers_sent() || ! self::keepsOut($this->store->current())) {
            return;
        }

        header('X-Robots-Tag: noindex', false);
    }
}
