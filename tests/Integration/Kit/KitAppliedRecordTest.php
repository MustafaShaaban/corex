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
    $this->second = (int) wp_insert_post([
        'post_type'   => 'page',
        'post_status' => 'publish',
        'post_title'  => 'Kit record probe two',
        'post_name'   => 'corex-118-kit-record-probe-two',
    ]);
});

afterEach(function () {
    wp_delete_post($this->page, true);
    wp_delete_post($this->second, true);
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

it('reads a kit applied from its pages on a site from before the record, and not once a reset removed it', function () {
    $record = new KitAppliedRecord();
    $slugs  = ['corex-118-kit-record-probe', 'corex-118-kit-record-probe-two'];

    update_post_meta($this->page, BlueprintActivator::PAGE_META, PageDisposition::PERSISTED_ADOPTED);
    update_post_meta($this->second, BlueprintActivator::PAGE_META, PageDisposition::PERSISTED_CREATED);
    $before = $record->isApplied('probe-kit', $slugs);

    // What a reset does to an adopted page: its content emptied and the kit's record taken away.
    delete_post_meta($this->page, BlueprintActivator::PAGE_META);

    expect($before)->toBeTrue()
        ->and($record->isApplied('probe-kit', $slugs))->toBeFalse()
        ->and(get_option(KitAppliedRecord::OPTION, null))->toBeNull();
});

it('does not read a partial apply as applied: one page written, another failed', function () {
    $record = new KitAppliedRecord();
    $slugs  = ['corex-118-kit-record-probe', 'corex-118-kit-record-probe-two'];

    // The activator records each page it writes; the second was never written.
    update_post_meta($this->page, BlueprintActivator::PAGE_META, PageDisposition::PERSISTED_CREATED);
    $recorded = $record->record('probe-kit', new ApplyOutcome(
        [
            ['disposition' => new PageDisposition('corex-118-kit-record-probe', 'One', PageDisposition::CREATE, 'absent'), 'pageId' => $this->page, 'isFront' => false, 'persistedAs' => 'created'],
            ['disposition' => new PageDisposition('corex-118-kit-record-probe-two', 'Two', PageDisposition::CREATE, 'absent'), 'pageId' => null, 'isFront' => false, 'persistedAs' => null],
        ],
        [],
        [],
        null,
    ));

    expect($recorded)->toBeFalse()
        ->and($record->isApplied('probe-kit', $slugs))->toBeFalse();
});

it('reads nothing from the pages once the site keeps the record', function () {
    $record = new KitAppliedRecord();
    update_option(KitAppliedRecord::OPTION, ['another-kit']);
    update_post_meta($this->page, BlueprintActivator::PAGE_META, PageDisposition::PERSISTED_CREATED);
    update_post_meta($this->second, BlueprintActivator::PAGE_META, PageDisposition::PERSISTED_CREATED);

    expect($record->isApplied('probe-kit', ['corex-118-kit-record-probe', 'corex-118-kit-record-probe-two']))->toBeFalse();
});
