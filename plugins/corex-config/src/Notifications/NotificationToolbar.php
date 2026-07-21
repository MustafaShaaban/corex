<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Config\AdminUi\CorexAdminAssets;
use Corex\Notifications\NotificationService;
use WP_Admin_Bar;

/**
 * The WordPress admin-toolbar notification entry (spec 072 FR-017). It makes notifications reachable
 * from anywhere the toolbar shows — non-CoreX admin screens and the front end — with the actor's real
 * unread count and a link to the center. It never shows on a CoreX screen (the shell header bell owns
 * that surface there, so the two never appear at once) and loads no admin bundle: it is a single
 * server-rendered node.
 */
final class NotificationToolbar
{
    private const VISUAL_CAP = 99;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly CorexAdminAssets $screens,
    ) {
    }

    public function register(): void
    {
        add_action('admin_bar_menu', [$this, 'addNode'], 80);
    }

    public function addNode(WP_Admin_Bar $bar): void
    {
        if (! is_user_logged_in() || ! current_user_can(CorexAbility::MANAGE_NOTIFICATIONS) || $this->onCorexScreen()) {
            return;
        }

        $count = $this->notifications->unreadCountForCurrentActor();
        $badge = $count > 0
            ? ' <span class="corex-toolbar-count">' . esc_html($this->display($count)) . '</span>'
            : '';

        $bar->add_node([
            'id'    => 'corex-notifications',
            'title' => '<span class="ab-icon dashicons dashicons-bell" aria-hidden="true"></span>'
                . '<span class="ab-label">' . esc_html($this->label($count)) . '</span>' . $badge,
            'href'  => admin_url('admin.php?page=corex-notifications'),
            'meta'  => ['title' => $this->label($count)],
        ]);
    }

    /** True on a CoreX admin screen, where the shell header bell already shows (never both). */
    private function onCorexScreen(): bool
    {
        if (! is_admin() || ! function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();

        return $screen !== null && $this->screens->supports($screen->id);
    }

    private function label(int $count): string
    {
        if ($count < 1) {
            return __('Notifications', 'corex');
        }

        return sprintf(
            /* translators: %d: number of unread notifications. */
            _n('Notifications, %d unread', 'Notifications, %d unread', $count, 'corex'),
            $count,
        );
    }

    private function display(int $count): string
    {
        return $count > self::VISUAL_CAP ? self::VISUAL_CAP . '+' : (string) $count;
    }
}
