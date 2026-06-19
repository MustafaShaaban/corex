<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Collection helpers for Free/Core vs Pro boundary rows.
 */
final class FreeProBoundaryMatrix
{
    /**
     * @param list<FreeProBoundaryItem> $items
     */
    private function __construct(private readonly array $items)
    {
    }

    /**
     * @param list<FreeProBoundaryItem> $items
     */
    public static function fromItems(array $items): self
    {
        foreach ($items as $item) {
            if (! $item instanceof FreeProBoundaryItem) {
                throw new InvalidArgumentException('Free/Core boundary matrices accept only FreeProBoundaryItem rows.');
            }
        }

        return new self(array_values($items));
    }

    /**
     * @return list<FreeProBoundaryItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function itemFor(string $capability): FreeProBoundaryItem
    {
        foreach ($this->items as $item) {
            if ($item->capability === $capability) {
                return $item;
            }
        }

        throw new InvalidArgumentException(sprintf('Free/Core boundary capability "%s" is not defined.', $capability));
    }

    /**
     * @param list<string> $requiredCapabilities
     *
     * @return list<string>
     */
    public function missingCapabilities(array $requiredCapabilities): array
    {
        $present = array_fill_keys(array_map(
            static fn (FreeProBoundaryItem $item): string => $item->capability,
            $this->items,
        ), true);

        return array_values(array_filter(
            $requiredCapabilities,
            static fn (string $capability): bool => ! isset($present[$capability]),
        ));
    }

    /**
     * @return list<string>
     */
    public function securityCriticalProCandidates(): array
    {
        return array_values(array_map(
            static fn (FreeProBoundaryItem $item): string => $item->capability,
            array_filter(
                $this->items,
                static fn (FreeProBoundaryItem $item): bool => $item->securityCritical
                    && $item->classification === FreeProBoundaryItem::CLASSIFICATION_PRO_CANDIDATE,
            ),
        ));
    }
}

