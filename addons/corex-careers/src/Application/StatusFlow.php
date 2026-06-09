<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers\Application;

defined('ABSPATH') || exit;

/**
 * The application pipeline: which status transitions are allowed. Pure.
 */
final class StatusFlow
{
    public const NEW         = 'new';
    public const REVIEWING   = 'reviewing';
    public const INTERVIEWED = 'interviewed';
    public const OFFER       = 'offer';
    public const HIRED       = 'hired';
    public const REJECTED    = 'rejected';

    private const TRANSITIONS = [
        self::NEW         => [self::REVIEWING, self::REJECTED],
        self::REVIEWING   => [self::INTERVIEWED, self::REJECTED],
        self::INTERVIEWED => [self::OFFER, self::REJECTED],
        self::OFFER       => [self::HIRED, self::REJECTED],
        self::HIRED       => [],
        self::REJECTED    => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public function nextStatuses(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }
}
