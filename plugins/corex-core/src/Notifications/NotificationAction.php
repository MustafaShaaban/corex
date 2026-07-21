<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

/**
 * A notification's direct action — navigation to the owning screen, never a mutation (spec 072
 * FR-012). The drawer follows the link; it does not perform dangerous state changes. A link renders
 * only when the actor passes the optional `ability`.
 */
final class NotificationAction
{
    private function __construct(
        public readonly string $labelKey,
        public readonly string $url,
        public readonly ?string $ability,
    ) {
    }

    public static function to(string $labelKey, string $url, ?string $ability = null): self
    {
        return new self($labelKey, $url, $ability);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['label_key' => $this->labelKey, 'url' => $this->url, 'ability' => $this->ability];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['label_key'] ?? ''),
            (string) ($data['url'] ?? ''),
            isset($data['ability']) ? (string) $data['ability'] : null,
        );
    }
}
