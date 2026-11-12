<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

use Corex\Provisioning\PageDisposition;

/**
 * One page a kit touched, as the reset command found it: its id and title, how the kit persisted it
 * (`_corex_kit_page`: created, adopted, replaced, suffixed, or '' for a page seeded before dispositions
 * were recorded), and whether the kit recorded what the page was before it changed it (spec 115).
 * A pure value the planner reads.
 */
final class ResetPage
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $persistedAs,
        public readonly bool $hasRecord,
    ) {
    }

    /** A page the owner had before the kit: the kit replaced or adopted it. */
    public function wasTheOwners(): bool
    {
        return $this->persistedAs === PageDisposition::PERSISTED_REPLACED
            || $this->persistedAs === PageDisposition::PERSISTED_ADOPTED;
    }
}
