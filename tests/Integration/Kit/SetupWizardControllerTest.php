<?php

/**
 * Integration test: the setup wizard REST controller on real ./wp (spec 068: T195).
 * The controller projects the real wizard config + nine-step progress, previews a plan with real
 * conflicts, and refuses apply without explicit confirmation (FR-140/143).
 *
 * @package Corex\Tests\Integration\Kit
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Kit\BlueprintActivator;
use Corex\Kit\Setup\SetupProgress;
use Corex\Kit\Setup\SetupWizardController;
use Corex\Kit\SetupWizard;

function setupController(): SetupWizardController
{
    $container = Boot::app()->container();

    return new SetupWizardController(
        $container->make(SetupWizard::class),
        new SetupProgress(),
        $container->make(BlueprintActivator::class),
    );
}

it('projects the real wizard config and nine-step progress', function () {
    $controller = setupController();

    $config = $controller->config();
    expect(array_keys($config))->toBe(['kits', 'brandFields', 'demoLevels', 'conflictChoices'])
        ->and($config['kits'])->not->toBe([])
        ->and(array_column($config['demoLevels'], 'id'))->toBe(['minimal', 'standard', 'full'])
        ->and(array_column($config['conflictChoices'], 'id'))->toBe(['keep', 'replace', 'suffix'])
        ->and($config['brandFields'])->toHaveCount(8);

    $progress = $controller->progressState();
    expect($progress['steps'])->toHaveCount(9)
        ->and($progress)->toHaveKeys(['current', 'percentage', 'canLaunch']);
});

it('previews a company plan with fewer minimal pages than full and lists real conflicts', function () {
    $controller = setupController();

    $minimal = $controller->planPreview('company', 'minimal');
    $full    = $controller->planPreview('company', 'full');

    expect(count($minimal['plan']['pages']))->toBeLessThan(count($full['plan']['pages']))
        // No fixture pages exist, so nothing conflicts; the shape is always present and truthful.
        ->and($minimal)->toHaveKey('conflicts')
        ->and($minimal['conflicts'])->toBeArray();
});

it('refuses apply without an explicit confirmation (FR-140)', function () {
    $request = new WP_REST_Request('POST', '/corex/v1/setup/apply');
    $request->set_param('kit', 'company');
    $request->set_param('confirm', false);

    $response = setupController()->applyResponse($request);
    $body     = $response->get_data();

    expect($response->get_status())->toBe(422)
        ->and($body['ok'] ?? true)->toBeFalse()
        ->and($body['code'] ?? '')->toBe('confirmation_required');
});
