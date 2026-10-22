<?php

/**
 * A sub-site cannot switch off an add-on that is active for the whole network (spec 114,
 * US-SAFE-08, FR-021).
 *
 * The Add-ons screen called WordPress's deactivation with its network default, so a sub-site
 * administrator pressing the switch of a network-active add-on turned it off on every site. The
 * screen now draws that switch locked, and the toggle service refuses the request if it comes
 * anyway. Asked of a fresh request on the second site, where corex-ui is network-active.
 *
 * @package Corex\Tests\Multisite
 */

declare(strict_types=1);

$attemptOnSubSite = <<<'PHP'
$container = \Corex\Boot::app()->container();
$manager   = $container->make(\Corex\Config\Addons\AddonManager::class);
$addon     = $container->make(\Corex\Config\Addons\AddonRegistry::class)->find('corex-ui');
$scope     = $manager->state()->scopeOf('corex-ui');
$result    = $container->make(\Corex\Config\Addons\AddonToggleService::class)->disable($addon, $scope, 0);
echo 'COREX_MS_JSON:' . wp_json_encode([
    'scope'     => $scope->value,
    'succeeded' => $result->succeeded,
    'reason'    => $result->reason,
]);
PHP;

$networkActive = <<<'PHP'
echo 'COREX_MS_JSON:' . wp_json_encode([
    'network_active' => array_key_exists('corex-ui/corex-ui.php', (array) get_site_option('active_sitewide_plugins', [])),
]);
PHP;

it('refuses to switch a network-active add-on off from a sub-site, and leaves it on for the network', function () use ($attemptOnSubSite, $networkActive) {
    $siteTwo = $this->siteIdForPath('/site2/');

    $attempt = $this->wpCliJson($siteTwo, $attemptOnSubSite);

    expect($attempt['scope'])->toBe('network')
        ->and($attempt['succeeded'])->toBeFalse()
        ->and($attempt['reason'])->toBe('Managed by the network administrator')
        ->and($this->wpCliJson(1, $networkActive)['network_active'])->toBeTrue();
});
