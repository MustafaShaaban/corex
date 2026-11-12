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
use Corex\Config\Readiness\LaunchChecklist;
use Corex\Config\Readiness\ReadinessWords;
use Corex\Notifications\NotificationService;
use DateTimeImmutable;

/**
 * The CoreX Command Center dashboard widget (spec 072 Phase C, T023). A server-rendered summary on the
 * WordPress dashboard: the site's operating state, how many notifications need the actor's attention,
 * and the current readiness/security snapshot — each a navigation-only link into CoreX, never an
 * action. It runs only local checks (no remote calls, FR-015) and reuses the bounded counts the
 * Notification Center and readiness factory already expose.
 *
 * Its readiness row is the one launch checklist (spec 118): the same "N of M ready" as Operations,
 * what still blocks going live, and each open check by name in the gate's words.
 */
final class CommandCenterWidget
{
    public function __construct(
        private readonly OperationsMode $mode,
        private readonly OperationsModeStore $modeStore,
        private readonly NotificationService $notifications,
        private readonly ProductionReadinessSnapshotFactory $readiness,
        private readonly LaunchChecklist $checklist,
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
        $launch   = $this->checklist->build(
            $this->readiness->fromCurrentSite(new DateTimeImmutable('now')),
            ReadinessWords::GATE,
        );

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
                esc_html($launch['count']),
                esc_html($launch['blockingText']),
                admin_url('admin.php?page=corex-operations-security&tab=environment'),
                esc_html__('Review readiness', 'corex'),
            )
            . $this->openChecks($launch['groups'])
            . '</div>';
    }

    /**
     * The checks that are not ready, each linked to its fix and said in words.
     *
     * @param list<array{rows:list<array{label:string,word:string,href:string,state:string}>}> $groups
     */
    private function openChecks(array $groups): string
    {
        $items = '';
        foreach ($groups as $group) {
            foreach ($group['rows'] as $row) {
                if ($row['href'] === '') {
                    continue;
                }

                $items .= '<li><a href="' . esc_url($row['href']) . '">' . esc_html($row['label']) . '</a>: '
                    . esc_html($row['word']) . '</li>';
            }
        }

        return $items === '' ? '' : '<ul class="corex-command-center__checks">' . $items . '</ul>';
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
