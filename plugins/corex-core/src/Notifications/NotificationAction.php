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
 * only when the actor passes the optional `ability`, which
 * {@see \Corex\Config\Notifications\WpNotificationRepository::present()} enforces by withholding the
 * action from the payload rather than trusting the client to hide it.
 *
 * **`label` and `labelKey` are not the same thing, and both are carried.** `labelKey` is a
 * translation key; nothing in this pipeline resolves keys to strings, so a payload holding only a
 * key gave the client nothing to render and every action fell through to a hardcoded "Open"
 * (spec 087, FR-010). `label` is the already-translated string the client shows. The key stays for
 * callers that have one and for a resolver that may exist one day.
 */
final class NotificationAction
{
    private function __construct(
        public readonly string $labelKey,
        public readonly string $url,
        public readonly ?string $ability,
        public readonly string $label,
    ) {
    }

    public static function to(
        string $labelKey,
        string $url,
        ?string $ability = null,
        string $label = '',
    ): self {
        return new self($labelKey, $url, $ability, $label);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'label_key' => $this->labelKey,
            'label'     => $this->label,
            'url'       => $this->url,
            'ability'   => $this->ability,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['label_key'] ?? ''),
            (string) ($data['url'] ?? ''),
            isset($data['ability']) ? (string) $data['ability'] : null,
            (string) ($data['label'] ?? ''),
        );
    }
}
