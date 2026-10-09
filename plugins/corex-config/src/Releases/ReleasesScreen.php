<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Admin\AdminPage;
use Corex\Config\AdminUi\ScreenAsset;

/**
 * The CoreX → Releases screen (spec 107): what this site is running, what stands in the way of
 * installing a release here, and a package given to the site and read.
 *
 * The screen is the shell and a mount; what it shows is asked of the Releases routes. It is open
 * to whoever the routes are open to, and to nobody else: {@see ReleaseRestGateway::mayManage()}
 * is the one rule for both, so the menu cannot offer a screen whose every request is refused.
 */
final class ReleasesScreen
{
    public const SLUG = 'corex-releases';

    private string $hook = '';

    public function __construct(
        private readonly ReleaseRestGateway $gateway,
        private readonly AdminPage $page,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function menu(): void
    {
        // On a network a release is the network's to install, from the main site. A site
        // administrator there is not shown a screen that would only refuse them.
        if (is_multisite() && ! $this->gateway->mayManage()) {
            return;
        }

        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('CoreX Releases', 'corex'),
            __('Releases', 'corex'),
            CorexAbility::MANAGE_RELEASES,
            self::SLUG,
            [$this, 'render'],
            59,
        );
    }

    public function render(): void
    {
        if (! $this->gateway->mayManage()) {
            echo $this->page->permissionDenied('releases');

            return;
        }

        echo $this->page->open(
            'releases',
            __('Releases', 'corex'),
            __('See which release this site is running, and give it a new one.', 'corex'),
        ) . '<div id="corex-releases-app" class="corex-releases"></div>' . $this->page->close();
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        $dir = dirname(__DIR__, 2);

        wp_enqueue_style(
            'corex-releases',
            plugins_url('assets/releases.css', $dir . '/corex-config.php'),
            ['corex-admin-shell'],
            ScreenAsset::version($dir . '/assets/releases.css'),
        );

        $asset = is_file($dir . '/build/admin/index.asset.php')
            ? require $dir . '/build/admin/index.asset.php'
            : ['dependencies' => [], 'version' => 'dev'];

        wp_enqueue_script(
            'corex-releases-screen',
            plugins_url('build/admin/index.js', $dir . '/corex-config.php'),
            [...$asset['dependencies'], 'corex-runtime'],
            $asset['version'],
            true,
        );
        wp_set_script_translations('corex-releases-screen', 'corex');
        wp_localize_script('corex-releases-screen', 'corexReleases', [
            'restUrl' => esc_url_raw(untrailingslashit(rest_url('corex/v1/releases'))),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }
}
