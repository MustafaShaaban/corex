<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Exception;

/**
 * When a flow takes answers and what it tells a visitor when it does not (spec 122, US-VIS-01, US-VIS-02).
 *
 * It belongs to the flow and not to one of its versions: saved, it takes effect at once, without
 * publishing, so clearing a date reopens an expired flow and the closed message can be corrected
 * while the flow is closed (DECISIONS #477). The dates are instants, kept in UTC; the editor shows
 * and takes them in the site's time zone.
 */
final readonly class FlowAvailability
{
    public const MAX_MESSAGE_LENGTH = 500;

    public function __construct(
        public ?DateTimeImmutable $opensAt = null,
        public ?DateTimeImmutable $closesAt = null,
        public ?int $maxResponses = null,
        public string $closedMessage = '',
        public FlowAlternative $alternative = new FlowAlternative(),
    ) {
    }

    public static function fromArray(mixed $stored): self
    {
        $stored = is_array($stored) ? $stored : [];
        $cap = $stored['max_responses'] ?? null;

        return new self(
            self::instant($stored['opens_at'] ?? null),
            self::instant($stored['closes_at'] ?? null),
            is_numeric($cap) && (int) $cap > 0 ? (int) $cap : null,
            trim((string) ($stored['closed_message'] ?? '')),
            FlowAlternative::fromArray(is_array($stored['alternative'] ?? null) ? $stored['alternative'] : []),
        );
    }

    /** @return array{opens_at:?string,closes_at:?string,max_responses:?int,closed_message:string,alternative:array<string,string>} */
    public function toArray(): array
    {
        return [
            'opens_at' => $this->opensAt?->format(DATE_ATOM),
            'closes_at' => $this->closesAt?->format(DATE_ATOM),
            'max_responses' => $this->maxResponses,
            'closed_message' => $this->closedMessage,
            'alternative' => $this->alternative->toArray(),
        ];
    }

    /** @throws DomainException With the reason the owner reads. */
    public function assertValid(): void
    {
        if ($this->opensAt !== null && $this->closesAt !== null && $this->closesAt <= $this->opensAt) {
            throw new DomainException(__('The closing date must be after the opening date.', 'corex'));
        }
        if (mb_strlen($this->closedMessage) > self::MAX_MESSAGE_LENGTH) {
            throw new DomainException(__('The closed message is too long. Keep it under 500 characters.', 'corex'));
        }
        $this->alternative->assertValid();
    }

    private static function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception) {
            return null;
        }
    }
}
