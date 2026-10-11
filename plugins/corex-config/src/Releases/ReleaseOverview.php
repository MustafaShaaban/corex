<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Everything the Releases screen shows when it is opened (spec 107, US4 and US5; FR-043,
 * FR-044, FR-051).
 *
 * What the desk says of the site, the host and the packages; the release that can be gone back
 * to, with the table copies and when they go; and what happened here. Opening the screen is
 * also when the installer tidies, so what is shown is what is there afterwards.
 */
final class ReleaseOverview
{
    public function __construct(
        private readonly ReleaseDesk $desk,
        private readonly ReleaseUpkeep $upkeep,
        private readonly ReleaseReturn $return,
        private readonly ReleaseRecord $record,
    ) {
    }

    /**
     * @return array<string,mixed> The desk's overview, and `previous`, `record`, `package_days`.
     *
     * @throws ReleaseRefused When an installation is in progress that this release cannot read.
     */
    public function whenOpened(DateTimeImmutable $now): array
    {
        $this->upkeep->whenTheScreenOpens($now);

        return [
            ...$this->desk->overview(),
            'previous'     => $this->return->kept(),
            'record'       => $this->record->entries(),
            'package_days' => ReleaseUpkeep::PACKAGE_DAYS,
        ];
    }
}
