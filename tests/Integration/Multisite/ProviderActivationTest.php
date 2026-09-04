<?php

/**
 * Regression test for the Boot::activePlugins() network-activation defect fixed in Phase 5 by
 * commit 01409b4. The test boots a fresh WordPress request for every site because one PHP process
 * cannot rerun Boot's provider lifecycle after switch_to_blog().
 *
 * @package Corex\Tests\Integration\Multisite
 */

declare(strict_types=1);

$providerState = <<<'PHP'
$container = \Corex\Boot::app()->container();
$inspector = $container->make(\Corex\Multisite\PluginActivationInspector::class);
echo 'COREX_MS_JSON:' . wp_json_encode([
    'site_id' => get_current_blog_id(),
    'ui_loaded' => $container->has(\Corex\Ui\Blocks\PostsProvider::class),
    'email_loaded' => $container->has(\Corex\Email\Driver\MailDriver::class),
    'email_scope' => $inspector->scopeOf('corex-email/corex-email.php')->value,
]);
PHP;

it('loads network providers everywhere and a site provider only on its site', function () use ($providerState) {
    $siteIds = [
        1,
        $this->siteIdForPath('/site2/'),
        $this->siteIdForPath('/site3/'),
    ];
    $states = array_map(
        fn (int $siteId): array => $this->wpCliJson($siteId, $providerState),
        $siteIds,
    );

    expect(array_column($states, 'site_id'))->toBe($siteIds)
        ->and(array_column($states, 'ui_loaded'))->toBe([true, true, true])
        ->and(array_column($states, 'email_loaded'))->toBe([false, true, false])
        ->and(array_column($states, 'email_scope'))->toBe(['none', 'site', 'none']);
});
