<?php

/**
 * Add-ons screen docs links + tier badges (company-site readiness, Parts 4-5). The rendered
 * "Documentation" link must be an absolute URL (so it never resolves against the active client
 * WordPress domain) and open safely in a new tab; the advisory tier badge must render its label.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Admin\AdminPage;
use Corex\Config\Addons\AddonActivator;
use Corex\Config\Addons\AddonManager;
use Corex\Config\Addons\AddonRegistry;
use Corex\Config\Addons\AddonsScreen;
use Corex\Config\Addons\PendingKits;
use Corex\Config\Docs\DocsUrl;
use Corex\Provisioning\KitProvisioner;
use Corex\Security\Admin\AdminGuard;
use Corex\Support\Config\ConfigInterface;

beforeEach(function () {
    Functions\when('__')->returnArg(1);
    Functions\when('esc_attr')->returnArg(1);
    Functions\when('esc_html')->returnArg(1);
    Functions\when('esc_html__')->returnArg(1);
    Functions\when('esc_url')->returnArg(1);
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value) => $value);
});

/**
 * The Add-ons screen wired with a real DocsUrl whose config returns the given docs base.
 */
function addonsScreenWithDocsBase(string $base): AddonsScreen
{
    $registry = new AddonRegistry();
    $bare     = static fn (string $class): object => (new ReflectionClass($class))->newInstanceWithoutConstructor();

    $config = new class ($base) implements ConfigInterface {
        public function __construct(private string $base)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'docs.base_url' ? $this->base : $default;
        }

        public function has(string $key): bool
        {
            return $key === 'docs.base_url';
        }
    };

    return new AddonsScreen(
        $registry,
        new AddonManager($registry),
        $bare(AddonActivator::class),
        $bare(AdminGuard::class),
        Mockery::mock(KitProvisioner::class),
        $bare(PendingKits::class),
        new AdminPage(),
        new DocsUrl($config),
    );
}

function metaMarkup(AddonsScreen $screen, string $slug): string
{
    $addon  = (new AddonRegistry())->find($slug);
    $method = new ReflectionMethod($screen, 'renderMeta');
    $method->setAccessible(true);

    ob_start();
    $method->invoke($screen, $addon);

    return (string) ob_get_clean();
}

it('renders the documentation link as an absolute docs-site URL, not a client-relative path', function () {
    $markup = metaMarkup(addonsScreenWithDocsBase('http://docs.corex.local'), 'corex-media');

    expect($markup)
        ->toContain('href="http://docs.corex.local/guides/media/"')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        // the raw relative path must never be the href
        ->not->toContain('href="/guides/media/"');
});

it('falls back to an absolute GitHub docs URL when no docs base is configured', function () {
    $markup = metaMarkup(addonsScreenWithDocsBase(''), 'corex-media');

    expect($markup)
        ->toContain('href="https://github.com/')
        ->not->toContain('href="/guides/media/"');
});

it('renders the advisory tier badge with its label', function () {
    $screen = addonsScreenWithDocsBase('');
    $method = new ReflectionMethod($screen, 'tierBadge');
    $method->setAccessible(true);

    $badge = (string) $method->invoke($screen, (new AddonRegistry())->find('corex-kit-woo'));

    expect($badge)
        ->toContain('corex-badge--tier-requires_woocommerce')
        ->toContain('Requires WooCommerce');
});
