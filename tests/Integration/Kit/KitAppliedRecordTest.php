<?php

/**
 * One rule for whether a kit is applied, whichever way it was applied (spec 118, US-OVR-09).
 *
 * An apply that wrote every page is recorded; one that failed on a page is not; a site whose wizard
 * applied the kit before the record was kept is read as applied from the pages carrying the kit's
 * record, without applying anything; and once a reset has taken that record away, it is not.
 *
 * @package Corex\Tests\Integration\Kit
 */

declare(strict_types=1);

use Corex\Kit\BlueprintActivator;
use Corex\Kit\Provisioning\KitAppliedRecord;
use Corex\Provisioning\ApplyOutcome;
use Corex\Provisioning\PageDisposition;

beforeEach(function () {
    $this->saved = get_option(KitAppliedRecord::OPTION, null);
    delete_option(KitAppliedRecord::OPTION);
    $this->page = (int) wp_insert_post([
        'post_type'   => 'page',
        'post_status' => 'publish',
        'post_title'  => 'Kit record probe',
        'post_name'   => 'corex-118-kit-record-probe',
    ]);
});

afterEach(function () {
    wp_delete_post($this->page, true);
    $this->saved === null ? delete_option(KitAppliedRecord::OPTION) : update_option(KitAppliedRecord::OPTION, $this->saved);
});

function kitOutcome(?int $pageId): ApplyOutcome
{
    return new ApplyOutcome(
        [['disposition' => new PageDisposition('home', 'Home', PageDisposition::CREATE, 'absent'), 'pageId' => $pageId, 'isFront' => true, 'persistedAs' => null]],
        [],
        [],
        $pageId,
    );
}

it('records an apply that wrote every page, and not one that failed on a page', function () {
    $record = new KitAppliedRecord();

    expect($record->record('probe-kit', kitOutcome(null)))->toBeFalse()
        ->and($record->isApplied('probe-kit', []))->toBeFalse()
        ->and($record->record('probe-kit', kitOutcome(41)))->toBeTrue()
        ->and($record->isApplied('probe-kit', []))->toBeTrue();
});

it('reads a kit applied from a page carrying its record, and not once a reset removed it', function () {
    $record = new KitAppliedRecord();
    $slugs  = ['corex-118-kit-record-probe'];

    update_post_meta($this->page, BlueprintActivator::PAGE_META, PageDisposition::PERSISTED_ADOPTED);
    $before = $record->isApplied('probe-kit', $slugs);

    // What a reset does to an adopted page: its content emptied and the kit's record taken away.
    delete_post_meta($this->page, BlueprintActivator::PAGE_META);

    expect($before)->toBeTrue()
        ->and($record->isApplied('probe-kit', $slugs))->toBeFalse()
        ->and(get_option(KitAppliedRecord::OPTION, null))->toBeNull();
});
