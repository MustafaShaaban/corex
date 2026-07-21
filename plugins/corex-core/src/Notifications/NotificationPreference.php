<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

/**
 * A user's per-category in-app notification preference, within policy (spec 072 FR-020). Any category
 * defaults to shown; a user may mute a category — except the mandatory ones (security / system /
 * operations), which are always shown so a muted preference can never hide something the user must
 * see. Immutable and validated: unknown categories are dropped, not stored.
 */
final class NotificationPreference
{
    /** Categories a user can never mute — always delivered in-app. */
    private const MANDATORY = [
        NotificationCategory::SECURITY,
        NotificationCategory::SYSTEM,
        NotificationCategory::OPERATIONS,
    ];

    /** @param array<string,bool> $categories category => in-app enabled (only muted ones need storing) */
    private function __construct(private readonly array $categories)
    {
    }

    public static function defaults(): self
    {
        return new self([]);
    }

    /** @param array<string,mixed> $map category => truthy/falsy in-app preference */
    public static function fromMap(array $map): self
    {
        $clean = [];
        foreach ($map as $category => $enabled) {
            $category = (string) $category;
            if (NotificationCategory::isValid($category)) {
                $clean[$category] = (bool) $enabled;
            }
        }

        return new self($clean);
    }

    public function allowsInApp(string $category): bool
    {
        if ($this->isMandatory($category)) {
            return true;
        }

        return $this->categories[$category] ?? true;
    }

    public function isMandatory(string $category): bool
    {
        return in_array($category, self::MANDATORY, true);
    }

    /** @return array<string,bool> */
    public function toArray(): array
    {
        return $this->categories;
    }
}
