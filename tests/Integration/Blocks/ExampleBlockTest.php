<?php

/**
 * Integration: the engine registers the example block on init and a connector as
 * a block-bindings source, on the real ./wp install (spec US1/US2/US4: FR-005,
 * FR-011, SC-002, SC-005).
 *
 * @package Corex\Tests\Integration\Blocks
 */

declare(strict_types=1);

use Corex\Blocks\Connectors\ConnectorRegistry;
use Corex\Tests\Fixtures\Blocks\FakeRepository;
use Corex\Tests\Fixtures\Blocks\TestConnector;

require_once dirname(__DIR__, 2) . '/Unit/Blocks/BlockFixtures.php';

it('registers the discovered example block on init', function () {
    expect(did_action('init'))->toBeGreaterThan(0)
        ->and(WP_Block_Type_Registry::get_instance()->is_registered('corex/entity-field'))->toBeTrue();
});

it('registers the example block style for conditional (per-block) enqueue', function () {
    $type = WP_Block_Type_Registry::get_instance()->get_registered('corex/entity-field');

    expect($type)->not->toBeNull()
        ->and($type->style_handles)->not->toBeEmpty(); // declared in block.json → loaded only when present
});

it('registers a connector as a block-bindings source', function () {
    (new ConnectorRegistry())->register(new TestConnector(new FakeRepository(null)));

    expect(WP_Block_Bindings_Registry::get_instance()->is_registered('corex/test'))->toBeTrue();
});
