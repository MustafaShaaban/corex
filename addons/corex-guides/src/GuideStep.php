<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides;

defined('ABSPATH') || exit;

/**
 * One numbered instruction (spec 084, FR-007).
 *
 * `$instruction` and `$result` are separate fields rather than one paragraph because the pair is
 * what makes a step checkable: the reader does the first and can tell whether it worked from the
 * second. A guide written as prose reads fine and leaves somebody stuck halfway with no way to know
 * whether they are stuck.
 *
 * `$warning` is for the steps that are hard to undo. It renders before the instruction, not after —
 * a caution a reader meets once they have already done the thing is decoration.
 */
final class GuideStep
{
    public function __construct(
        public readonly string $instruction,
        public readonly string $result = '',
        public readonly string $warning = '',
        public readonly ?GuideScreenshot $screenshot = null,
    ) {
    }

    public function hasWarning(): bool
    {
        return trim($this->warning) !== '';
    }

    /**
     * The step's words, for the screen's client-side search.
     */
    public function searchText(): string
    {
        return trim($this->instruction . ' ' . $this->result . ' ' . $this->warning);
    }
}
