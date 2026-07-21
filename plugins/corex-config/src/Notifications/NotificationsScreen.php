<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Admin\AdminPage;
use Corex\Security\Admin\AdminGuard;

/**
 * The full CoreX → Notifications screen (spec 072 US1, FR-018). A menu entry under CoreX that renders
 * the shared shell with a React mount for the bounded, filtered notification center. Gated on
 * {@see CorexAbility::MANAGE_NOTIFICATIONS}; the list itself is actor-scoped by the REST boundary.
 */
final class NotificationsScreen
{
    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
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
        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('CoreX Notifications', 'corex'),
            __('Notifications', 'corex'),
            CorexAbility::MANAGE_NOTIFICATIONS,
            'corex-notifications',
            [$this, 'render'],
            58,
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized(CorexAbility::MANAGE_NOTIFICATIONS)) {
            echo $this->page->permissionDenied('notifications');

            return;
        }

        echo $this->page->open(
            'notifications',
            __('Notifications', 'corex'),
            __('Everything CoreX needs your attention on — filter, review, and clear.', 'corex'),
        ) . '<div id="corex-notifications-app" class="corex-notifications-screen"></div>' . $this->page->close();
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        $dir = dirname(__DIR__, 2);
        $base = $dir . '/corex-config.php';
        $asset = is_file($dir . '/build/admin/index.asset.php')
            ? require $dir . '/build/admin/index.asset.php'
            : ['dependencies' => [], 'version' => 'dev'];

        wp_enqueue_script(
            'corex-notifications-screen',
            plugins_url('build/admin/index.js', $base),
            [...$asset['dependencies'], 'corex-runtime'],
            $asset['version'],
            true,
        );
        wp_set_script_translations('corex-notifications-screen', 'corex');
    }
}
