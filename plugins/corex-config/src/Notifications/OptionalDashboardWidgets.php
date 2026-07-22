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
use Corex\Support\Config\ConfigInterface;

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

    /** How many notifications the attention widget lists — bounded, like every other read (FR-026). */
    private const ATTENTION_LIMIT = 5;

    /**
     * How many unresolved notifications to look at to find those the actor has not read.
     *
     * `unread_only` on the query means "unresolved" at the storage layer; whether *this* actor has
     * read one is per-user state carried on each presented item. So the widget scans a bounded page
     * and filters, rather than trusting the flag to mean something it does not.
     */
    private const ATTENTION_SCAN = 25;

    public function __construct(
        private readonly ?NotificationService $notifications = null,
        private readonly ?OperationsMode $mode = null,
        private readonly ?OperationsModeStore $modeStore = null,
        private readonly ?ConfigInterface $config = null,
    ) {
    }

    /**
     * The declared widgets. Each entry states the ability it needs and whether it is Development-only,
     * so {@see shouldRegister()} has a rule to enforce for every id it accepts.
     *
     * @return array<string,array{title:string,ability:string,developmentOnly:bool,render:string,configKey:string}>
     */
    public static function catalogue(): array
    {
        return [
            self::ATTENTION   => [
                'title'           => __('CoreX Attention', 'corex'),
                'ability'         => CorexAbility::MANAGE_NOTIFICATIONS,
                'developmentOnly' => false,
                'render'          => 'renderAttention',
                'configKey'       => 'dashboard.widgets.attention',
            ],
            self::DEVELOPMENT => [
                'title'           => __('CoreX Development', 'corex'),
                'ability'         => CorexAbility::MANAGE_OPERATIONS,
                'developmentOnly' => true,
                'render'          => 'renderDevelopment',
                'configKey'       => 'dashboard.widgets.development',
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
        $mode = $this->modeStore?->current() ?? OperationsMode::PRODUCTION;

        foreach (self::catalogue() as $id => $definition) {
            $optedIn = $this->isEnabled($definition['configKey']);
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
     * Whether the site opted into this widget.
     *
     * Read through Config rather than a bespoke option so the toggle is an ordinary CoreX setting:
     * SettingsRegistry declares it, the settings form saves it to the option the Config engine
     * already reads (`dashboard.widgets.attention` → `corex_dashboard_widgets_attention`), and there
     * is no second place for the value to live. Absent means off — opt-in, not opt-out.
     */
    private function isEnabled(string $configKey): bool
    {
        return (string) ($this->config?->get($configKey, '') ?? '') === '1';
    }

    /**
     * The actor's unread, undismissed notifications, newest first, capped at ATTENTION_LIMIT.
     *
     * `forCurrentActor()` returns an envelope — {items, total, page, per_page} — not a bare list, so
     * the items must be taken out of it; iterating the envelope itself would walk its scalar keys.
     *
     * @return list<array<string,mixed>>
     */
    private function unreadForActor(): array
    {
        $page = $this->notifications?->forCurrentActor(
            NotificationQuery::fromRequest(['unread_only' => true], 1, self::ATTENTION_SCAN)
        );

        $items = is_array($page['items'] ?? null) ? $page['items'] : [];

        $unread = array_values(array_filter($items, static function (array $item): bool {
            $state = is_array($item['user_state'] ?? null) ? $item['user_state'] : [];

            return ($state['read'] ?? false) === false && ($state['dismissed'] ?? false) === false;
        }));

        return array_slice($unread, 0, self::ATTENTION_LIMIT);
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
        $items = $this->unreadForActor();

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
