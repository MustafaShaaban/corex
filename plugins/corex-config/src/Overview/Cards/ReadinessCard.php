<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview\Cards;

defined('ABSPATH') || exit;

use Corex\Config\Operations\ProductionReadinessSnapshotFactory;
use Corex\Config\Overview\OverviewCard;
use Corex\Config\Readiness\ChecklistMarkup;
use Corex\Config\Readiness\LaunchChecklist;
use Corex\Config\Readiness\ReadinessWords;
use Corex\Multisite\PluginActivationInspector;
use DateTimeImmutable;

/**
 * The Overview's launch readiness: the one checklist (spec 118, US-OVR-01/02) as a to-do list. Its
 * two groups, each row with a chip that says Done, Pending, Blocked or Could not check, open rows
 * linked to their fix, and a header with "N of M", a progress bar and, while the kit add-on is
 * active, a link to the Setup Wizard.
 */
final class ReadinessCard implements OverviewCard
{
    public const WIZARD_PLUGIN = 'corex-kit-company/corex-kit-company.php';

    public function __construct(
        private readonly ProductionReadinessSnapshotFactory $readiness,
        private readonly LaunchChecklist $checklist,
        private readonly ChecklistMarkup $markup,
        private readonly PluginActivationInspector $plugins,
    ) {
    }

    public function key(): string
    {
        return 'readiness';
    }

    public function title(): string
    {
        return __('Launch readiness', 'corex');
    }

    public function render(array $settingValues): string
    {
        $launch = $this->checklist->build(
            $this->readiness->fromCurrentSite(new DateTimeImmutable('now')),
            ReadinessWords::TODO,
        );

        $groups = '';
        foreach ($launch['groups'] as $group) {
            $groups .= '<h3 class="corex-checklist__group-head"><span>' . esc_html($group['title']) . '</span>'
                . '<span class="corex-checklist__group-count">' . esc_html($group['count']) . '</span></h3>'
                . $this->markup->rows($group['rows']);
        }

        return '<section class="corex-surface corex-overview__card corex-overview__readiness" data-card="readiness">'
            . '<header class="corex-overview__card-head"><h2>' . esc_html($this->title()) . '</h2>'
            . '<span class="corex-overview__count">' . esc_html($launch['count']) . '</span>'
            . $this->wizardLink() . '</header>'
            . $this->markup->progress($this->title(), $launch['done'], $launch['total'], $launch['count'])
            . $groups . '</section>';
    }

    private function wizardLink(): string
    {
        if (! $this->plugins->isActive(self::WIZARD_PLUGIN) || ! current_user_can('manage_options')) {
            return '';
        }

        return '<a class="corex-overview__link" href="' . esc_url(admin_url('admin.php?page=corex-setup')) . '">'
            . esc_html__('Setup Wizard', 'corex') . ' &rarr;</a>';
    }
}
