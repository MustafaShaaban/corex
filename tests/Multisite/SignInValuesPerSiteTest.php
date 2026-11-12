<?php

/**
 * The same username failing on two sites of a network is stored as different values (spec 124,
 * US-SEC-10 scenario 4, FR-019).
 *
 * A network's sites share one `wp-config.php`, so a key made from its salts alone would be the same
 * on every site, and a site's table could be matched against another's. The key mixes in the site.
 *
 * @package Corex\Tests\Multisite
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Security\LoginProtection\LoginAttemptTable;
use Corex\Config\Security\LoginProtection\LoginHashKey;
use Corex\Config\Security\LoginProtection\LoginProtectionContext;
use Corex\Config\Security\LoginProtection\LoginProtectionPolicy;
use Corex\Config\Security\LoginProtection\LoginProtectionService;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\WpLoginAccounts;
use Corex\Config\Security\LoginProtection\WpLoginAttemptStore;
use Corex\Database\Schema\Migrator;

it('US-SEC-10 scenario 4: the same username failing once on each of two sites is stored as different values', function () {
    $siteOne = 1;
    $siteTwo = $this->siteIdForPath('/site2/');
    $container = Boot::app()->container();
    $policy = new LoginProtectionPolicy(
        new LoginProtectionSettings(
            enabled: true,
            customSlug: 'team-login',
            blockDefaultEndpoints: false,
            threshold: 5,
            windowSeconds: 300,
            lockoutSeconds: 900,
            trustedProxyMode: false,
            trustedProxyRanges: [],
            retainDays: 30,
            successfulLoginLogging: false,
        ),
        $container->make(LoginHashKey::class),
    );
    $service = new LoginProtectionService($policy, $container->make(WpLoginAttemptStore::class), new WpLoginAccounts());
    $context = new LoginProtectionContext('same-username-on-both', '198.51.100.78', 'CoreX Test Browser');
    $migrator = new Migrator();

    $stored = function (int $siteId) use ($migrator, $policy, $context): array {
        return $this->onSite($siteId, static function () use ($migrator, $policy, $context): array {
            global $wpdb;

            return $wpdb->get_row($wpdb->prepare(
                'SELECT identity_hash, network_hash FROM ' . $migrator->fullName(LoginAttemptTable::NAME) . ' WHERE network_hash = %s',
                $policy->networkHash($context),
            ), ARRAY_A) ?? [];
        });
    };
    $forget = function (int $siteId) use ($migrator, $policy, $context): void {
        $this->onSite($siteId, static function () use ($migrator, $policy, $context): void {
            global $wpdb;

            $wpdb->delete($migrator->fullName(LoginAttemptTable::NAME), ['network_hash' => $policy->networkHash($context)]);
        });
    };

    try {
        foreach ([$siteOne, $siteTwo] as $siteId) {
            $this->onSite($siteId, static function () use ($migrator, $service, $context): void {
                $migrator->create((new LoginAttemptTable())->schema());
                $service->recordFailure($context, new DateTimeImmutable('now'));
            });
        }

        $one = $stored($siteOne);
        $two = $stored($siteTwo);

        expect($one)->toHaveKeys(['identity_hash', 'network_hash'])
            ->and($two)->toHaveKeys(['identity_hash', 'network_hash'])
            ->and($one['identity_hash'])->not->toBe($two['identity_hash'])
            ->and($one['network_hash'])->not->toBe($two['network_hash']);
    } finally {
        $forget($siteOne);
        $forget($siteTwo);
    }
});
