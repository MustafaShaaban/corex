<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use Exception;

/**
 * One time a flow version was made the live version: which, when and by whom (spec 122, FR-002).
 *
 * The author is 0 for the live version of a flow published before publications were recorded.
 */
final readonly class FlowPublication
{
    public function __construct(
        public int $versionNumber,
        public int $publishedBy,
        public DateTimeImmutable $publishedAt,
    ) {
    }

    /** @return array{version:int,by:int,at:string} */
    public function toArray(): array
    {
        return [
            'version' => $this->versionNumber,
            'by' => $this->publishedBy,
            'at' => $this->publishedAt->format(DATE_ATOM),
        ];
    }

    /**
     * The publications a stored payload lists, leaving out any entry that cannot be read.
     *
     * @return list<self>
     */
    public static function listFrom(mixed $stored): array
    {
        $publications = [];
        foreach (is_array($stored) ? $stored : [] as $entry) {
            if (! is_array($entry) || (int) ($entry['version'] ?? 0) < 1 || ! is_string($entry['at'] ?? null)) {
                continue;
            }
            try {
                $publications[] = new self((int) $entry['version'], max(0, (int) ($entry['by'] ?? 0)), new DateTimeImmutable($entry['at']));
            } catch (Exception) {
                continue;
            }
        }

        return $publications;
    }
}
