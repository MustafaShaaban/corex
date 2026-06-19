<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Collection helpers for component coverage readiness rows.
 */
final class ComponentCoverageMatrix
{
    /**
     * Needs where WordPress core blocks/styles or patterns should win before new custom block scope.
     *
     * @var list<string>
     */
    private const NATIVE_FIRST_NEEDS = [
        'home',
        'about',
        'services',
        'media',
        'navigation',
        'page templates',
    ];

    /**
     * @param list<ComponentCoverageItem> $items
     */
    private function __construct(private readonly array $items)
    {
    }

    /**
     * @param list<ComponentCoverageItem> $items
     */
    public static function fromItems(array $items): self
    {
        foreach ($items as $item) {
            if (! $item instanceof ComponentCoverageItem) {
                throw new InvalidArgumentException('Component coverage matrices accept only ComponentCoverageItem rows.');
            }
        }

        return new self(array_values($items));
    }

    /**
     * @return list<ComponentCoverageItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function itemFor(string $need): ComponentCoverageItem
    {
        foreach ($this->items as $item) {
            if ($item->need === $need) {
                return $item;
            }
        }

        throw new InvalidArgumentException(sprintf('Component coverage need "%s" is not defined.', $need));
    }

    /**
     * @param list<string> $requiredNeeds
     *
     * @return list<string>
     */
    public function missingNeeds(array $requiredNeeds): array
    {
        $present = array_fill_keys(array_map(
            static fn (ComponentCoverageItem $item): string => $item->need,
            $this->items,
        ), true);

        return array_values(array_filter(
            $requiredNeeds,
            static fn (string $need): bool => ! isset($present[$need]),
        ));
    }

    /**
     * @return list<string>
     */
    public function unknownMechanisms(): array
    {
        $knownMechanisms = array_fill_keys(ComponentCoverageItem::knownMechanisms(), true);
        $unknownMechanisms = [];

        foreach ($this->items as $item) {
            if (! isset($knownMechanisms[$item->mechanism])) {
                $unknownMechanisms[] = sprintf('%s:%s', $item->need, $item->mechanism);
            }
        }

        return $unknownMechanisms;
    }

    /**
     * @return list<string>
     */
    public function nativeFirstViolations(): array
    {
        $violations = [];

        foreach ($this->items as $item) {
            if (
                in_array($item->need, self::NATIVE_FIRST_NEEDS, true)
                && $item->mechanism === ComponentCoverageItem::MECHANISM_COREX_BLOCK
                && str_contains(strtolower($item->source), 'new custom block')
            ) {
                $violations[] = sprintf('%s:%s', $item->need, $item->source);
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function visualRedesignItems(): array
    {
        $items = [];

        foreach ($this->items as $item) {
            if (str_contains(strtolower($item->source), 'visual redesign')) {
                $items[] = sprintf('%s:%s', $item->need, $item->source);
            }
        }

        return $items;
    }
}
