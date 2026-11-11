<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Whether a flow takes answers right now, and why not when it does not (spec 122, FR-027).
 *
 * Worked out when a page is served and when a submission arrives, from the stored state, the
 * flow's dates and its response count, so it holds on a site whose WordPress cron never runs.
 */
final readonly class FlowStatus
{
    public const DRAFT = 'draft';
    public const OPEN = 'open';
    public const SCHEDULED = 'scheduled';
    public const CLOSED = 'closed';
    public const EXPIRED = 'expired';

    public const BY_HAND = 'manual';
    public const BY_DATE = 'date';
    public const BY_CAP = 'cap';

    public function __construct(
        public string $state,
        public string $reason = '',
        public ?DateTimeImmutable $at = null,
        public ?int $responses = null,
    ) {
    }

    public function isOpen(): bool
    {
        return $this->state === self::OPEN;
    }

    /** Closed, expired or not yet open: a visitor is told so (US-VIS-01). */
    public function tellsVisitors(): bool
    {
        return in_array($this->state, [self::CLOSED, self::EXPIRED, self::SCHEDULED], true);
    }

    /** @return array{state:string,reason:string,at:?string,responses:?int} */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'reason' => $this->reason,
            'at' => $this->at?->format(DATE_ATOM),
            'responses' => $this->responses,
        ];
    }
}
