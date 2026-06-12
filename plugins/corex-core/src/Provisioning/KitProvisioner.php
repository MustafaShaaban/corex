<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Provisioning;

defined('ABSPATH') || exit;

/**
 * The seam between the kit framework (which knows blueprints) and the consumers that drive activation — the
 * Add-ons screen prompt and the dashboard card (spec 042). Defined in corex-core so corex-config depends only on
 * this interface, never on a kit add-on (Principle IX): corex-config resolves it *optionally* from the container
 * and degrades gracefully when no kit framework is active. The kit framework binds a concrete implementation.
 */
interface KitProvisioner
{
    /**
     * Kits that declare applicable starter content (pages/flags/modules).
     *
     * @return list<KitSummary>
     */
    public function applicableKits(): array;

    /**
     * Whether $kit is a known applicable kit.
     */
    public function isApplicable(string $kit): bool;

    /**
     * The kit name an add-on slug provisions (e.g. `corex-kit-company` → `company`), or null when the add-on is
     * not a kit. Lets a consumer map an enabled add-on to its kit without knowing the kit framework's internals.
     */
    public function kitForModule(string $addonSlug): ?string;

    /**
     * Read-only: what applying $kit would do. Performs no writes.
     */
    public function preview(string $kit): ApplyPreview;

    /**
     * Apply $kit through the single shared apply path; returns the outcome.
     */
    public function apply(string $kit): ApplyOutcome;
}
