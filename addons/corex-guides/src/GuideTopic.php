<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides;

defined('ABSPATH') || exit;

/**
 * One task a reader came to accomplish (spec 084, FR-006).
 *
 * A topic is a job, not a screen: "answer a submission" rather than "the Submissions inbox". Guides
 * organised by screen tell somebody what a screen contains, which they can already see; guides
 * organised by job tell them what to do, which is why they opened the guide.
 */
final class GuideTopic
{
    /** @param list<GuideStep> $steps */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $intro = '',
        public readonly array $steps = [],
    ) {
    }

    /**
     * @param list<GuideStep> $steps
     */
    public static function for(string $id, string $title, string $intro, array $steps): self
    {
        return new self($id, $title, $intro, $steps);
    }

    /**
     * Everything a reader could search this topic by — its own words and its steps'.
     */
    public function searchText(): string
    {
        $words = [$this->title, $this->intro];

        foreach ($this->steps as $step) {
            $words[] = $step->searchText();
        }

        return trim(implode(' ', array_filter($words, static fn (string $w): bool => trim($w) !== '')));
    }
}
