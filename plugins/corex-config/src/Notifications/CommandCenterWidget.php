<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Config\Operations\ProductionReadinessSnapshotFactory;
use Corex\Notifications\NotificationService;
use DateTimeImmutable;

/**
 * The CoreX Command Center dashboard widget (spec 072 Phase C, T023). A server-rendered summary on the
 * WordPress dashboard: the site's operating state, how many notifications need the actor's attention,
 * and the current readiness/security snapshot — each a navigation-only link into CoreX, never an
 * action. It runs only local checks (no remote calls, FR-015) and reuses the bounded counts the
 * Notification Center and readiness factory already expose.
 */
final class CommandCenterWidget
{
    public function __construct(
        private readonly OperationsMode $mode,
        private readonly OperationsModeStore $modeStore,
        private readonly NotificationService $notifications,
        private readonly ProductionReadinessSnapshotFactory $readiness,
    ) {
    }

    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'add']);
    }

    public function add(): void
    {
        if (! current_user_can(CorexAbility::MANAGE_ADMIN) && ! current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget('corex_command_center', __('CoreX Command Center', 'corex'), [$this, 'render']);
    }

    public function render(): void
    {
        $state    = $this->mode->describe($this->modeStore->current());
        $unread   = $this->notifications->unreadCountForCurrentActor();
        $blockers = count($this->readiness->fromCurrentSite(new DateTimeImmutable('now'))->blockingKeys());

        echo '<div class="corex-command-center">' // phpcs:ignore WordPress.Security.EscapeOutput -- each field is escaped below.
            . $this->row(
                esc_html__('Site state', 'corex'),
                esc_html((string) $state['label']),
                esc_html((string) $state['detail']),
                admin_url('admin.php?page=corex-operations-security'),
                esc_html__('Operations & Security', 'corex'),
            )
            . $this->row(
                esc_html__('Attention', 'corex'),
                esc_html($this->countLabel($unread, __('nothing unread', 'corex'))),
                '',
                admin_url('admin.php?page=corex-notifications'),
                esc_html__('Open Notifications', 'corex'),
            )
            . $this->row(
                esc_html__('Readiness', 'corex'),
                esc_html($this->countLabel($blockers, __('no blockers', 'corex'))),
                '',
                admin_url('admin.php?page=corex-operations-security'),
                esc_html__('Review readiness', 'corex'),
            )
            . '</div>';
    }

    private function countLabel(int $count, string $none): string
    {
        return $count > 0 ? (string) $count : $none;
    }

    /** All text arguments are pre-escaped by the caller; $url is escaped here. Reads cleanly under the
     * dashboard's default styles (this widget is not on a CoreX shell screen). */
    private function row(string $label, string $value, string $detail, string $url, string $linkText): string
    {
        return '<p class="corex-command-center__row">'
            . '<strong>' . $label . ':</strong> ' . $value
            . ($detail === '' ? '' : ' — <span class="corex-command-center__detail">' . $detail . '</span>')
            . ' · <a href="' . esc_url($url) . '">' . $linkText . '</a></p>';
    }
}
