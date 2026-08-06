<?php

/**
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

use Brain\Monkey\Functions;
use Corex\Config\Notifications\WpNotificationPreferenceStore;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationPreference;

beforeEach(function () {
    $this->hadWpdb = array_key_exists('wpdb', $GLOBALS);
    $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
});

afterEach(function () {
    if ($this->hadWpdb) {
        $GLOBALS['wpdb'] = $this->previousWpdb;

        return;
    }

    unset($GLOBALS['wpdb']);
});

it('derives the notification preference key for each install scope', function (
    bool $multisite,
    bool $mainSite,
    string $blogPrefix,
    string $expectedKey,
) {
    $readKeys = [];
    $writtenKeys = [];

    Functions\when('is_multisite')->justReturn($multisite);
    Functions\when('is_main_site')->justReturn($mainSite);
    Functions\when('get_user_meta')->alias(
        static function (int $userId, string $key, bool $single) use (&$readKeys): array {
            $readKeys[] = $key;

            return [NotificationCategory::JOBS => false];
        },
    );
    Functions\when('update_user_meta')->alias(
        static function (int $userId, string $key, array $preferences) use (&$writtenKeys): int {
            $writtenKeys[] = $key;

            return 1;
        },
    );

    $GLOBALS['wpdb'] = new class ($blogPrefix) {
        public function __construct(private readonly string $blogPrefix)
        {
        }

        public function get_blog_prefix(): string
        {
            return $this->blogPrefix;
        }
    };

    $store = new WpNotificationPreferenceStore();
    $preference = $store->forUser(7);
    $store->save(7, NotificationPreference::fromMap([NotificationCategory::JOBS => false]));

    expect($preference->allowsInApp(NotificationCategory::JOBS))->toBeFalse()
        ->and($readKeys)->toBe([$expectedKey])
        ->and($writtenKeys)->toBe([$expectedKey]);
})->with([
    'single-site' => [false, true, 'wp_', 'corex_notification_preferences'],
    'network main site' => [true, true, 'wp_', 'corex_notification_preferences'],
    'network subsite 2' => [true, false, 'wp_2_', 'wp_2_corex_notification_preferences'],
]);
