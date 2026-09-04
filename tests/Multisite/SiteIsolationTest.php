<?php

/**
 * Site-scoped settings, branding, and notification preferences (spec 100 T070).
 *
 * @package Corex\Tests\Multisite
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Branding\BrandingService;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationPreference;
use Corex\Notifications\NotificationPreferenceStore;
use Corex\Support\Config\ConfigInterface;

it('does not leak settings branding or notification preferences between sites', function () {
    $siteOne = 1;
    $siteTwo = $this->siteIdForPath('/site2/');
    $userId = 1;
    $container = Boot::app()->container();
    $config = $container->make(ConfigInterface::class);
    $branding = $container->make(BrandingService::class);
    $preferences = $container->make(NotificationPreferenceStore::class);

    $clear = function (int $siteId) use ($userId): void {
        $this->onSite($siteId, static function () use ($userId): void {
            global $wpdb;

            delete_option('corex_multisite_fixture');
            delete_option('corex_brand_footer_text');
            $metaKey = is_main_site()
                ? 'corex_notification_preferences'
                : $wpdb->get_blog_prefix() . 'corex_notification_preferences';
            delete_user_meta($userId, $metaKey);
        });
    };
    $clear($siteOne);
    $clear($siteTwo);

    try {
        $this->onSite($siteOne, static function () use ($userId, $preferences): void {
            update_option('corex_multisite_fixture', 'main-only');
            update_option('corex_brand_footer_text', 'Main brand');
            $preferences->save(
                $userId,
                NotificationPreference::fromMap([NotificationCategory::EMAIL => false]),
            );
        });

        $main = $this->onSite($siteOne, static fn (): array => [
            $config->get('multisite.fixture'),
            $branding->configuredFooterText(),
            $preferences->forUser($userId)->allowsInApp(NotificationCategory::EMAIL),
        ]);
        $second = $this->onSite($siteTwo, static fn (): array => [
            $config->get('multisite.fixture'),
            $branding->configuredFooterText(),
            $preferences->forUser($userId)->allowsInApp(NotificationCategory::EMAIL),
        ]);
        $restored = $this->onSite($siteOne, static fn (): array => [
            $config->get('multisite.fixture'),
            $branding->configuredFooterText(),
            $preferences->forUser($userId)->allowsInApp(NotificationCategory::EMAIL),
        ]);

        expect($main)->toBe(['main-only', 'Main brand', false])
            ->and($second)->toBe([null, '', true])
            ->and($restored)->toBe($main);
    } finally {
        $clear($siteOne);
        $clear($siteTwo);
    }
});
