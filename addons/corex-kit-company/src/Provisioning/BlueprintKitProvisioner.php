<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit\Provisioning;

use Corex\Kit\BlueprintActivator;
use Corex\Kit\BlueprintRegistry;
use Corex\Kit\SetupWizard;
use Corex\Provisioning\ApplyOutcome;
use Corex\Provisioning\ApplyPreview;
use Corex\Provisioning\KitProvisioner;
use Corex\Provisioning\KitSummary;

defined('ABSPATH') || exit;

/**
 * The kit framework's implementation of the corex-core {@see KitProvisioner} seam (spec 042): it maps the
 * registered blueprints to applicable kits, computes a read-only apply preview via the shared classifier, and
 * runs apply through the one shared {@see BlueprintActivator}. Bound to the interface in the container so
 * corex-config can drive activation without depending on this add-on. "Applied" is kept by {@see KitAppliedRecord},
 * the one rule the Setup Wizard records its applies through too (spec 118); the transient prompt ("pending")
 * state is owned by the Add-ons screen, not here.
 */
final class BlueprintKitProvisioner implements KitProvisioner
{
    public const APPLIED_OPTION = KitAppliedRecord::OPTION;

    private const MODULE_PREFIX = 'corex-kit-';

    public function __construct(
        private readonly BlueprintRegistry $registry,
        private readonly SetupWizard $wizard,
        private readonly BlueprintActivator $activator,
        private readonly KitAppliedRecord $applied = new KitAppliedRecord(),
    ) {
    }

    /**
     * @return list<KitSummary>
     */
    public function applicableKits(): array
    {
        $kits = [];

        foreach ($this->registry->all() as $blueprint) {
            $pages = $blueprint->pages();

            if ($pages === []) {
                continue; // not an applicable kit — declares no starter content
            }

            $name     = $blueprint->name();
            $kits[]   = new KitSummary(
                $name,
                ucfirst($name),
                $this->applied->isApplied($name, array_column($pages, 'slug')),
                count($pages),
                $blueprint->requiredModules(),
            );
        }

        return $kits;
    }

    public function isApplicable(string $kit): bool
    {
        $blueprint = $this->registry->find($kit);

        return $blueprint !== null && $blueprint->pages() !== [];
    }

    public function kitForModule(string $addonSlug): ?string
    {
        $name = str_starts_with($addonSlug, self::MODULE_PREFIX)
            ? substr($addonSlug, strlen(self::MODULE_PREFIX))
            : $addonSlug;

        return $this->isApplicable($name) ? $name : null;
    }

    public function preview(string $kit): ApplyPreview
    {
        $plan  = $this->wizard->plan($kit);
        $pages = $plan['pages'] ?? [];

        $frontTargetSlug = null;
        foreach ($pages as $page) {
            if (($page['front'] ?? false) === true) {
                $frontTargetSlug = $page['slug'];

                break;
            }
        }

        return new ApplyPreview(
            $kit,
            $this->activator->classify($pages),
            $frontTargetSlug,
            $plan['modules'],
            $plan['flags'],
        );
    }

    public function apply(string $kit): ApplyOutcome
    {
        $outcome = $this->activator->apply($this->wizard->plan($kit));

        $this->applied->record($kit, $outcome);

        return $outcome;
    }
}
