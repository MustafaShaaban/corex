<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications;

defined('ABSPATH') || exit;

use Corex\Notifications\NotificationService;

/**
 * The notification bell in the CoreX admin shell header (spec 072 FR-016). A real, keyboard-operable
 * button showing the current actor's unread count — capped visually at 99+, with the true count kept
 * in the accessible label. It contributes through the `corex_admin_header_actions` filter, so it
 * appears only inside the CoreX shell (the WordPress-toolbar entry for other screens is separate).
 *
 * This slice renders the bell and its live count; the drawer it opens is added next. The button
 * already carries the `data-corex-notification-bell` hook and dialog ARIA the drawer will bind to.
 */
final class NotificationBell
{
    private const VISUAL_CAP = 99;

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function register(): void
    {
        add_filter('corex_admin_header_actions', [$this, 'append']);
    }

    public function append(string $actions): string
    {
        return $actions . $this->render();
    }

    public function render(): string
    {
        $count = $this->notifications->unreadCountForCurrentActor();

        return sprintf(
            '<button type="button" class="corex-notification-bell" data-corex-notification-bell '
            . 'aria-haspopup="dialog" aria-expanded="false" aria-label="%1$s">'
            . '<span class="corex-notification-bell__icon" aria-hidden="true">%2$s</span>%3$s</button>',
            esc_attr($this->label($count)),
            $this->icon(),
            $this->badge($count),
        );
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

    private function badge(int $count): string
    {
        if ($count < 1) {
            return '';
        }

        $display = $count > self::VISUAL_CAP ? self::VISUAL_CAP . '+' : (string) $count;

        return '<span class="corex-notification-bell__badge" aria-hidden="true">' . esc_html($display) . '</span>';
    }

    private function icon(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>';
    }
}
