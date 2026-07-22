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
use Corex\Notifications\NotificationQuery;
use Corex\Notifications\NotificationService;

/**
 * The optional, opt-in Dashboard widgets (spec 072 US7, T024).
 *
 * The Command Center widget is registered for everyone with CoreX visibility (FR-023). These are the
 * opposite: nothing here appears unless the site asked for it. FR-025 sets four independent
 * conditions, and {@see shouldRegister()} is the one place all four are decided —
 *
 *   - opt-in: absent from the option means absent from the dashboard;
 *   - ability: each widget declares the ability it needs, checked per actor;
 *   - data: never register for an actor who can see nothing, because an empty widget takes space to
 *     say nothing and implies the actor is missing something they are simply not party to;
 *   - mode: a Development-only widget must never reach a Production dashboard.
 *
 * That method is pure so each rule is testable without WordPress, mirroring how LoginRouteGuard
 * separates its decision from its hooks. Everything rendered here reuses the canonical services the
 * full CoreX screens use — no widget queries anything for itself — and every action is a link.
 */
final class OptionalDashboardWidgets
{
    /** The actor's own unread notifications, in more detail than the Command Center's one-line count. */
    public const ATTENTION = 'corex_attention';

    /** The current operating mode and its warnings, for a site that is not live yet. */
    public const DEVELOPMENT = 'corex_development';

    /** Site option holding the enabled widget ids. Absent/empty means every optional widget is off. */
    public const OPTION = 'corex_dashboard_optional_widgets';

    /** How many notifications the attention widget lists — bounded, like every other read (FR-026). */
    private const ATTENTION_LIMIT = 5;

    public function __construct(
        private readonly ?NotificationService $notifications = null,
        private readonly ?OperationsMode $mode = null,
        private readonly ?OperationsModeStore $modeStore = null,
    ) {
    }

    /**
     * The declared widgets. Each entry states the ability it needs and whether it is Development-only,
     * so {@see shouldRegister()} has a rule to enforce for every id it accepts.
     *
     * @return array<string,array{title:string,ability:string,developmentOnly:bool,render:string}>
     */
    public static function catalogue(): array
    {
        return [
            self::ATTENTION   => [
                'title'           => __('CoreX Attention', 'corex'),
                'ability'         => CorexAbility::MANAGE_NOTIFICATIONS,
                'developmentOnly' => false,
                'render'          => 'renderAttention',
            ],
            self::DEVELOPMENT => [
                'title'           => __('CoreX Development', 'corex'),
                'ability'         => CorexAbility::MANAGE_OPERATIONS,
                'developmentOnly' => true,
                'render'          => 'renderDevelopment',
            ],
        ];
    }

    /**
     * Whether this widget may be added to the current actor's dashboard (FR-025).
     *
     * Pure by design: the caller resolves the four inputs from WordPress, and every rule below can
     * therefore fail on its own in a unit test. Unknown ids fail closed — an id with no catalogue
     * entry has no declared ability or mode rule, so there is nothing to enforce for it.
     */
    public function shouldRegister(
        string $widgetId,
        bool $enabled,
        bool $permitted,
        bool $hasData,
        string $mode,
    ): bool {
        $definition = self::catalogue()[$widgetId] ?? null;

        if ($definition === null || ! $enabled || ! $permitted || ! $hasData) {
            return false;
        }

        // Deliberately Development only, not "anything that is not Production": a staging site is a
        // rehearsal for production and should look like one.
        return $definition['developmentOnly'] === false || $mode === OperationsMode::DEVELOPMENT;
    }

    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'add']);
    }

    /** WordPress owns Screen Options for anything registered here, so a user can still hide one. */
    public function add(): void
    {
        $enabled = $this->enabledIds();
        $mode    = $this->modeStore?->current() ?? OperationsMode::PRODUCTION;

        foreach (self::catalogue() as $id => $definition) {
            $optedIn = in_array($id, $enabled, true);
            // Short-circuited deliberately: hasData() costs a bounded query, and a widget nobody
            // opted into — the default for every site — must not spend one on every dashboard load
            // just to decide not to appear.
            $permitted = $optedIn && (current_user_can($definition['ability']) || current_user_can('manage_options'));
            $hasData   = $permitted && $this->hasData($id);

            if (! $this->shouldRegister($id, $optedIn, $permitted, $hasData, $mode)) {
                continue;
            }

            wp_add_dashboard_widget($id, $definition['title'], [$this, $definition['render']]);
        }
    }

    /**
     * The opted-in widget ids.
     *
     * @return list<string>
     */
    private function enabledIds(): array
    {
        $stored = get_option(self::OPTION, []);

        if (! is_array($stored)) {
            return [];
        }

        // Only ids this class actually declares — a stale or hand-edited option cannot conjure a
        // widget that has no ability rule attached to it.
        return array_values(array_intersect(
            array_map('strval', $stored),
            array_keys(self::catalogue())
        ));
    }

    /** Whether this actor has anything for the widget to show (FR-025). */
    private function hasData(string $widgetId): bool
    {
        if ($widgetId === self::ATTENTION) {
            return ($this->notifications?->unreadCountForCurrentActor() ?? 0) > 0;
        }

        // The Development widget's content is the mode itself, which always exists; the mode rule in
        // shouldRegister() is what keeps it off the dashboards it does not belong on.
        return $this->modeStore !== null && $this->mode !== null;
    }

    public function renderAttention(): void
    {
        $items = $this->notifications?->forCurrentActor(
            NotificationQuery::fromRequest(['unread_only' => true], 1, self::ATTENTION_LIMIT)
        ) ?? [];

        if ($items === []) {
            // Only reachable if everything was read between registration and render.
            echo '<p>' . esc_html__('You are all caught up.', 'corex') . '</p>';

            return;
        }

        echo '<ul class="corex-attention-widget">';

        foreach ($items as $item) {
            $rendered = is_array($item['rendered'] ?? null) ? $item['rendered'] : [];
            echo '<li>' . esc_html((string) ($rendered['title'] ?? '')) . '</li>';
        }

        echo '</ul><p><a href="' . esc_url(admin_url('admin.php?page=corex-notifications')) . '">'
            . esc_html__('Open Notifications', 'corex') . '</a></p>';
    }

    public function renderDevelopment(): void
    {
        $current  = $this->modeStore?->current() ?? OperationsMode::DEVELOPMENT;
        $state    = $this->mode?->describe($current) ?? ['label' => $current];
        $warnings = $this->mode?->warnings($current) ?? [];

        echo '<p><strong>' . esc_html__('Mode', 'corex') . ':</strong> '
            . esc_html((string) ($state['label'] ?? $current)) . '</p>';

        if ($warnings !== []) {
            echo '<ul class="corex-development-widget__warnings">';

            foreach ($warnings as $warning) {
                echo '<li>' . esc_html((string) $warning) . '</li>';
            }

            echo '</ul>';
        }

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=corex-operations-security')) . '">'
            . esc_html__('Operations & Security', 'corex') . '</a></p>';
    }
}
