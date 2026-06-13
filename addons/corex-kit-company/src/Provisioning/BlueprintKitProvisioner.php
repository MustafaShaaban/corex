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
 * corex-config can drive activation without depending on this add-on. "Applied" state is tracked in an option;
 * the transient prompt ("pending") state is owned by the Add-ons screen, not here.
 */
final class BlueprintKitProvisioner implements KitProvisioner
{
    public const APPLIED_OPTION = 'corex_kit_applied';

    private const MODULE_PREFIX = 'corex-kit-';

    public function __construct(
        private readonly BlueprintRegistry $registry,
        private readonly SetupWizard $wizard,
        private readonly BlueprintActivator $activator,
    ) {
    }

    /**
     * @return list<KitSummary>
     */
    public function applicableKits(): array
    {
        $applied = $this->appliedNames();
        $kits    = [];

        foreach ($this->registry->all() as $blueprint) {
            $pages = $blueprint->pages();

            if ($pages === []) {
                continue; // not an applicable kit — declares no starter content
            }

            $name     = $blueprint->name();
            $kits[]   = new KitSummary(
                $name,
                ucfirst($name),
                in_array($name, $applied, true),
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

        $this->markApplied($kit);

        return $outcome;
    }

    private function markApplied(string $kit): void
    {
        $applied = $this->appliedNames();

        if (! in_array($kit, $applied, true)) {
            $applied[] = $kit;
            update_option(self::APPLIED_OPTION, array_values($applied));
        }
    }

    /**
     * @return list<string>
     */
    private function appliedNames(): array
    {
        return array_values(array_map('strval', (array) get_option(self::APPLIED_OPTION, [])));
    }
}
