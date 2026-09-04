<?php

/**
 * Real-network prefix resolution (spec 100 T066).
 *
 * @package Corex\Tests\Integration\Multisite
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Database\Schema\Migrator;

it('resolves foundation table prefixes from the current site', function () {
    $migrator = Boot::app()->container()->make(Migrator::class);
    $siteTwo = $this->siteIdForPath('/site2/');
    $siteThree = $this->siteIdForPath('/site3/');

    expect($migrator->fullName('activity'))->toBe('cxms_corex_activity')
        ->and($this->onSite($siteTwo, fn (): string => $migrator->fullName('activity')))
        ->toBe('cxms_' . $siteTwo . '_corex_activity')
        ->and($this->onSite($siteThree, fn (): string => $migrator->fullName('activity')))
        ->toBe('cxms_' . $siteThree . '_corex_activity');
});
