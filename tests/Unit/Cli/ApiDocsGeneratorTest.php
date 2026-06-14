<?php

/**
 * Unit tests for the OpenAPI emitter (spec 046: US3, FR-007).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Docs\ApiDocsGenerator;
use Corex\Cli\Routes\RouteDescriptor;

beforeEach(function () {
    $this->doc = (new ApiDocsGenerator())->generate([
        new RouteDescriptor('corex/v1', '/data/submissions', 'GET', true),
        new RouteDescriptor('app/v1', '/project', 'GET, POST', false),
    ], 'Corex API', '0.25.0');
});

it('emits a valid OpenAPI 3 envelope with info and paths', function () {
    expect($this->doc['openapi'])->toStartWith('3.')
        ->and($this->doc['info']['title'])->toBe('Corex API')
        ->and($this->doc['info']['version'])->toBe('0.25.0')
        ->and($this->doc)->toHaveKey('paths');
});

it('lists a path per route with the methods it accepts', function () {
    expect($this->doc['paths'])->toHaveKey('/corex/v1/data/submissions')
        ->and($this->doc['paths'])->toHaveKey('/app/v1/project')
        ->and($this->doc['paths']['/app/v1/project'])->toHaveKeys(['get', 'post']);
});

it('describes the spec-043 envelope as the response schema', function () {
    $schema = $this->doc['components']['schemas']['Envelope']['properties'];

    expect($schema)->toHaveKeys(['ok', 'message', 'data', 'code', 'errors']);
});

it('marks a guarded route with security and a public route without', function () {
    expect($this->doc['paths']['/corex/v1/data/submissions']['get'])->toHaveKey('security')
        ->and($this->doc['paths']['/corex/v1/data/submissions']['get']['security'])->not->toBe([])
        ->and($this->doc['paths']['/app/v1/project']['get']['security'])->toBe([]);
});

it('contains no secret', function () {
    $json = json_encode($this->doc);

    expect($json)->not->toContain('secret')
        ->and($json)->not->toContain('key=');
});
